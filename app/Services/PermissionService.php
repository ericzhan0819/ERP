<?php

namespace App\Services;

use App\Models\Module;
use App\Models\User;

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
     * 技術註解：base_permission 為 null 代表公開登入可見；非 null 則統一使用 Spatie can 判斷。
     */
    private function canViewModule(User $user, Module $module): bool
    {
        return $module->base_permission === null || $user->can($module->base_permission);
    }
}
