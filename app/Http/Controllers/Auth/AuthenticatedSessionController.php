<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        if ($request->user()->account_status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => '此帳號目前不可登入。',
            ]);
        }

        return redirect()->intended(route('employee-system.overview', absolute: false));
    }

    /**
     * 技術註解：登出時完整清除 session 並固定回到登入頁，確保狀態透明可預期。
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
