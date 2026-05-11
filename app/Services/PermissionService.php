<?php

namespace App\Services;

use App\Models\User;

class PermissionService
{
    /**
     * 技術註解：集中以 Spatie 權限判斷可見模組，避免前端與中介層知道 RBAC 細節。
     *
     * @return array<int, array<string, mixed>>
     */
    public function visibleModules(User $user): array
    {
        return collect(config('modules', []))
            ->filter(fn (array $module): bool => ($module['is_active'] ?? false) && $user->hasPermissionTo($module['permission'], 'web'))
            ->values()
            ->all();
    }

    /**
     * 技術註解：路由進入點只詢問此服務，不直接接觸 Spatie 權限 API。
     */
    public function canAccessModule(User $user, string $moduleKey): bool
    {
        $module = config("modules.{$moduleKey}");

        return is_array($module)
            && ($module['is_active'] ?? false)
            && $user->hasPermissionTo($module['permission'], 'web');
    }
}
