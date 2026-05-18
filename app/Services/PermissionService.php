<?php

namespace App\Services;

use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Role;
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
        $modules = Module::query()
            ->where('is_enabled', true)
            ->where('is_active', true)
            ->ordered()
            ->get([
                'id',
                'key',
                'label',
                'section',
                'parent_id',
                'route_name',
                'base_permission',
                'permission_prefix',
                'icon_key',
                'icon',
                'sort_order',
                'active_patterns',
            ])
            ->filter(fn (Module $module): bool => $this->canViewModule($user, $module))
            ->values();

        $byParentId = $modules->groupBy('parent_id');

        $buildNode = function (Module $module) use (&$buildNode, $byParentId): array {
            $children = $byParentId
                ->get($module->id, collect())
                ->sortBy('sort_order')
                ->map(fn (Module $child): array => $buildNode($child))
                ->values()
                ->all();

            return [
                'key' => $module->key,
                'label' => $module->label,
                'href' => $this->resolveModuleHref($module),
                'route_name' => $module->route_name,
                'icon_key' => $module->icon_key ?: $module->icon,
                'active' => $this->resolveModuleActive($module),
                'children' => $children,
                'sort_order' => $module->sort_order,
            ];
        };

        return $modules
            ->whereNull('parent_id')
            ->groupBy(fn (Module $module): string => $module->section ?: 'general')
            ->map(function ($sectionModules, string $section) use ($buildNode): array {
                return [
                    'section' => $section,
                    'items' => $sectionModules
                        ->sortBy('sort_order')
                        ->map(fn (Module $module): array => $buildNode($module))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * 技術註解：Dashboard 顯示能力採白名單輸出，避免將完整 permission 清單暴露到前端。
     *
     * @return array<string, bool>
     */
    public function getDashboardCapabilities(User $user): array
    {
        return [
            'dashboard.quick_actions' => $user->can('module.dashboard.quick-actions'),
            'dashboard.finance_summary' => $user->can('module.dashboard.finance-summary'),
            'dashboard.export_summary' => $user->can('module.dashboard.export-summary'),
            'dashboard.risk_panel' => $user->can('module.dashboard.risk-panel'),
        ];
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
     * 技術註解：角色建立集中於服務層，避免 Controller 分散處理唯一性與快取刷新，降低權限設定不一致風險。
     */
    public function createRole(string $name, ?string $label = null): Role
    {
        $role = Role::create([
            'name' => $name,
            'guard_name' => 'web',
            'label' => $label ?: $name,
        ]);

        $this->refreshPermissionCache();

        return $role;
    }

    /**
     * 技術註解：刪除角色需先檢查是否仍綁定使用者，避免產生孤兒授權狀態與存取邏輯漂移。
     */
    public function deleteRole(Role $role): void
    {
        if ($role->users()->exists()) {
            abort(422, '角色仍綁定使用者，無法刪除');
        }

        $role->delete();
        $this->refreshPermissionCache();
    }

    /**
     * 技術註解：角色基本資料更新集中於服務層，統一刷新 Spatie 權限快取，避免 RBAC 判斷使用過期角色名稱。
     */
    public function updateRoleMeta(Role $role, string $name, ?string $label = null): Role
    {
        $role->name = $name;
        $role->label = $label ?: $name;
        $role->save();

        $this->refreshPermissionCache();

        return $role->fresh();
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
        if ($module->base_permission === null) {
            return true;
        }

        if ($user->can($module->base_permission)) {
            return true;
        }

        $legacyPermission = $this->resolveLegacyPermissionName($module->base_permission);

        // 技術註解：為降低命名遷移期間風險，僅在主命名未命中時嘗試舊命名相容判斷。
        return $legacyPermission !== null && $user->can($legacyPermission);
    }

    /**
     * 技術註解：集中處理模組導向網址，若 route 不存在則回傳 null，避免前端自行推導。
     */
    private function resolveModuleHref(Module $module): ?string
    {
        if ($module->route_name === null) {
            return null;
        }

        if (!app('router')->has($module->route_name)) {
            return null;
        }

        return route($module->route_name);
    }

    /**
     * 技術註解：active 判斷字串由後端統一提供，避免 Sidebar/MobileSidebar 重複維護 route 判斷邏輯。
     *
     * @return array<int, string>
     */
    private function resolveModuleActive(Module $module): array
    {
        $patterns = Arr::wrap($module->active_patterns);

        if (is_string($module->route_name) && $module->route_name !== '') {
            $patterns[] = $module->route_name;
            $patterns[] = $module->route_name . '.*';
        }

        return collect($patterns)
            ->filter(fn ($pattern): bool => is_string($pattern) && $pattern !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 技術註解：支援舊命名（底線）到新命名（連字號）過渡，降低權限鍵變更造成的可見性中斷。
     */
    private function resolveLegacyPermissionName(string $permission): ?string
    {
        if (!str_contains($permission, '_')) {
            return null;
        }

        return str_replace('_', '-', $permission);
    }
}
