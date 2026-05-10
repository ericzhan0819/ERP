<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        Log::info('Inertia share auth diagnostics', [
            'auth_id' => Auth::id(),
            'request_user_id' => $user?->id,
            'session_id' => $request->session()->getId(),
            'path' => $request->path(),
            'route_name' => optional($request->route())->getName(),
        ]);

        if ($user) {
            Log::debug('Inertia shared auth user payload', [
                'user_id' => $user->id,
                'has_name' => filled($user->name),
                'has_email' => filled($user->email),
                'has_phone' => filled($user->phone),
            ]);
        }

        return [
            ...parent::share($request),
            'auth' => [
                // 技術註解：改為直接共享使用者資料，避免測試/序列化階段 lazy prop 未展開造成 auth.user 判定為 null。
                'user' => $request->user()?->only('id', 'name', 'email', 'phone'),
                'roles' => $user?->getRoleNames()?->values()?->all() ?? [],
                'permissions' => $user?->getAllPermissions()?->pluck('name')?->values()?->all() ?? [],
            ],
            'roles' => $user?->getRoleNames()?->values()?->all() ?? [],
            'permissions' => $user?->getAllPermissions()?->pluck('name')?->values()?->all() ?? [],
        ];
    }
}
