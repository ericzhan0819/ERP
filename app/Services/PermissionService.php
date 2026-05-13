<?php

namespace App\Services;

use App\Models\Module;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    /**
     * 技術註解：modules 資料表是模組入口唯一來源；此處集中計算 Inertia 可見清單，避免前端接觸 RBAC 細節。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getVisibleModules(User $user): array
    {
        return Module::query()
            ->active()
            ->ordered()
            ->get(['key', 'label', 'route_name', 'base_permission', 'icon', 'sort_order'])
            ->filter(fn (Module $module): bool => $this->canViewModule($user, $module))
            ->map(fn (Module $module): array => [
                'key' => $module->key,
                'label' => $module->label,
                'route_name' => $module->route_name,
                'icon' => $module->icon,
                'sort_order' => $module->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * 技術註解：路由進入點以 module key 查詢資料庫狀態，找不到、停用或無權限都視為不可進入。
     */
    public function canAccessModule(User $user, string $moduleKey): bool
    {
        $module = Module::query()->where('key', $moduleKey)->first();

        return $module instanceof Module
            && $module->is_active
            && $this->canViewModule($user, $module);
    }

    /**
     * 技術註解：集中同步單一角色，確保 Spatie 權限快取與使用者關聯在同一流程刷新。
     */
    public function syncUserRole(User $user, string $roleName): User
    {
        $user->syncRoles([$roleName]);

        return $this->refreshPermissionCache($user);
    }

    /**
     * 技術註解：集中同步直接權限，避免 Controller 分散呼叫 Spatie 寫入 API。
     *
     * @param array<int, string> $permissionNames
     */
    public function syncUserPermissions(User $user, array $permissionNames): User
    {
        $user->syncPermissions($permissionNames);

        return $this->refreshPermissionCache($user);
    }

    /**
     * 技術註解：角色與直接權限批次更新時只在結尾清除一次快取，降低重複刷新風險。
     *
     * @param array<int, string> $permissionNames
     */
    public function syncUserAccess(User $user, string $roleName, array $permissionNames): User
    {
        $user->syncRoles([$roleName]);
        $user->syncPermissions($permissionNames);

        return $this->refreshPermissionCache($user);
    }

    /**
     * 技術註解：統一清除 Spatie 權限快取；傳入使用者時同步重載 roles 與 permissions 關聯。
     */
    public function refreshPermissionCache(?User $user = null): ?User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user?->load('roles', 'permissions');
    }

    /**
     * 技術註解：base_permission 為 null 代表公開登入可見；非 null 則統一使用 Spatie can 判斷。
     */
    private function canViewModule(User $user, Module $module): bool
    {
        return $module->base_permission === null || $user->can($module->base_permission);
    }
}
