<?php

namespace App\Http\Middleware;

use App\Services\CompanyBrandService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly CompanyBrandService $companyBrandService,
    ) {}

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
                // 技術註解：僅傳遞必要身份欄位，避免前端獲得過量使用者資料。
                'user' => $user?->only('id', 'name', 'email'),
                // 技術註解：導覽由後端白名單輸出，前端僅負責展示。
                'visibleModules' => $user ? $this->permissionService->getVisibleModules($user) : [],
                // 技術註解：Dashboard 能力採最小白名單，不外洩完整 permission 陣列。
                'capabilities' => $user ? $this->permissionService->getDashboardCapabilities($user) : [],
            ],
            // 技術註解：集中共享登入後品牌資料，避免前端 Layout/Header/Sidebar 各自硬編碼品牌字串。
            'brand' => $this->companyBrandService->resolveForUser($user),
            // 技術註解：帳號狀態獨立於權限系統，僅提供前端呈現帳號可用狀態。
            'accountStatus' => $user?->account_status,
        ];
    }
}
