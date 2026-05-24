<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\LoginLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LoginLogService $loginLogService,
    )
    {
    }

    /**
     * 技術註解：登入頁由 Inertia 單一入口輸出，避免建立第二套 Blade 認證畫面。
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * 技術註解：只使用 Laravel session guard 驗證 email/password，不處理角色或權限。
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 技術註解：維持單一登入欄位 email，實際可輸入 email 或 phone，避免擴大前後端改動範圍。
        $loginInput = trim((string) $credentials['email']);
        $throttleKey = Str::transliterate(Str::lower($loginInput)).'|'.$request->ip();

        // 技術註解：使用 email(or phone 輸入值)+IP 作為節流鍵，降低暴力破解與撞庫攻擊風險，且不揭露帳號存在性。
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ]);
        }

        $loginField = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $attemptCredentials = [
            $loginField => $loginInput,
            'password' => $credentials['password'],
        ];

        if (! Auth::attempt($attemptCredentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);
            $this->loginLogService->recordFailed($request, $loginInput, 'invalid_credentials');

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // 技術註解：登入成功後立即清除節流計數，避免合法使用者被殘留節流狀態誤擋。
        RateLimiter::clear($throttleKey);

        $user = $request->user();

        // 技術註解：inactive 阻擋保留在認證流程內，成功驗證密碼後立即撤銷 session，不交由 middleware 補救。
        if ($user->is_active === false || $user->account_status !== 'active') {
            $this->loginLogService->recordInactive($request, $user, $loginInput);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated.',
            ]);
        }

        $request->session()->regenerate();

        // 技術註解：最後登入時間只在登入成功後寫入，避免每次 request 汙染真實登入事件。
        $loginAt = now();
        $user->forceFill(['last_login_at' => $loginAt])->save();
        $this->loginLogService->recordSuccess($request, $user, $loginInput);
        $this->auditLogService->log($user, 'auth.login.success', 'User logged in', $user, [
            'login_at' => $loginAt->toISOString(),
        ]);

        return redirect()->intended(route('employee-system.overview', absolute: false));
    }

    /**
     * 技術註解：登出時完整清除 session 並固定回到官網首頁，確保狀態透明可預期。
     */
    public function destroy(Request $request): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        $this->loginLogService->recordLogout($request, $user);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
