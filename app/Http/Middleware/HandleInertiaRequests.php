<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly PermissionService $permissionService) {}

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

        return [
            ...parent::share($request),
            'auth' => [
                // 技術註解：只共享 RBAC 驗證需要的最小資料，權限可見性委派 PermissionService。
                'user' => $user?->only('id', 'name', 'email'),
                'roles' => $user?->getRoleNames()->values()->all() ?? [],
                'visibleModules' => $user ? $this->permissionService->getVisibleModules($user) : [],
            ],
            // 技術註解：帳號狀態獨立於權限系統，僅提供前端呈現帳號可用狀態。
            'accountStatus' => $user?->account_status,
        ];
    }
}
