<?php

namespace App\Http\Middleware;

use App\Models\ModulePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

        $modulePermissions = [];

        if (Schema::hasTable('module_permissions')) {
            $modulePermissions = ModulePermission::query()
                ->orderBy('id')
                ->get([
                    'module_key',
                    'module_name',
                    'allowed_roles',
                    'allowed_user_ids',
                    'enabled',
                ])
                ->values()
                ->all();
}

        return [
            ...parent::share($request),
            'auth' => [
                // 技術註解：改為直接共享使用者資料，避免測試/序列化階段 lazy prop 未展開造成 auth.user 判定為 null。
                // 技術註解：Sidebar RBAC 需要固定拿到 id/role/permissions，避免前端判斷誤隱藏。
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->getRoleNames()->first(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                ] : null,
                'roles' => $user?->getRoleNames()?->values()?->all() ?? [],
                'permissions' => $user?->getAllPermissions()?->pluck('name')?->values()?->all() ?? [],
            ],
            'roles' => $user?->getRoleNames()?->values()?->all() ?? [],
            'permissions' => $user?->getAllPermissions()?->pluck('name')?->values()?->all() ?? [],
            'modulePermissions' => $modulePermissions,
        ];
    }
}
