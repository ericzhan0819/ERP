<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * 權限 action 白名單（僅允許統一命名的固定動作）。
     */
    private const ACTION_WHITELIST = ['view', 'create', 'update', 'delete', 'export', 'approve', 'void', 'manage'];

    /**
     * Seed the application's RBAC foundation.
     */
    public function run(): void
    {
        // 技術註解：重建最小 RBAC 快取，確保 fresh seed 後權限判斷立即一致。
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 技術註解：先建立預設租戶邊界，避免管理員或員工帳號落在 company_id=0 / branch_id=null 造成後續資料污染。
        $defaultCompany = Company::updateOrCreate(
            ['code' => 'OO'],
            ['name' => 'OO INTERNATIONAL']
        );

        // 技術註解：分店以 company + code 作為穩定鍵，可重複執行 seed 且不會建立重複資料。
        $defaultBranch = Branch::updateOrCreate(
            [
                'company_id' => $defaultCompany->id,
                'code' => 'MAIN',
            ],
            ['name' => 'Default Branch']
        );

        $modules = [
            'dashboard' => [
                'label' => '總覽',
                'section' => 'system',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.overview',
                'permission_prefix' => 'module.dashboard',
                'base_permission' => 'module.dashboard.view',
                'icon_key' => 'LayoutDashboard',
                'icon' => 'LayoutDashboard',
                'sort_order' => 10,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.overview'],
            ],
            'test-module' => [
                'label' => '測試模塊',
                'section' => 'testing',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.test-module',
                'permission_prefix' => 'module.test-module',
                'base_permission' => 'module.test-module.view',
                'icon_key' => 'FlaskConical',
                'icon' => 'FlaskConical',
                'sort_order' => 90,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.test-module'],
            ],
            'staff-permission' => [
                'label' => '員工權限管理',
                'section' => 'administration',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.staff-permissions.index',
                'permission_prefix' => 'staff-permission',
                'base_permission' => 'staff-permission.view',
                'icon_key' => 'ShieldCheck',
                'icon' => 'ShieldCheck',
                'sort_order' => 20,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => [
                    'employee-system.staff-permissions.*',
                ],
            ],
            'vehicles' => [
                'label' => '車輛管理',
                'section' => 'operations',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.vehicles.index',
                'permission_prefix' => 'module.vehicles',
                'base_permission' => 'module.vehicles.view',
                'icon_key' => 'car',
                'icon' => 'car',
                'sort_order' => 30,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['vehicles.*'],
            ],
            'audit' => [
                'label' => '稽核紀錄',
                'section' => 'administration',
                'parent_id' => null,
                'parent_key' => null,
                'route_name' => 'employee-system.audit.activity-logs',
                'permission_prefix' => 'module.audit',
                'base_permission' => 'module.audit.view',
                'icon_key' => 'ShieldCheck',
                'icon' => 'ShieldCheck',
                'sort_order' => 40,
                'is_enabled' => true,
                'is_active' => true,
                'active_patterns' => ['employee-system.audit.*'],
            ],
        ];

        foreach ($modules as $key => $module) {
            // 技術註解：updateOrCreate 讓模組註冊可重跑，並保持 seed 結果單一真實來源。
            Module::updateOrCreate(['key' => $key], $module);
        }

        $permissionDefinitions = [
            // 技術註解：主要邏輯已切換到 module.{module_key}.{action} 統一命名。
            'module.dashboard.view' => ['label' => '查看總覽', 'group' => '系統'],
            'module.staff.view' => ['label' => '查看員工資料', 'group' => '人事'],
            'module.permissions.view' => ['label' => '查看權限管理', 'group' => '權限管理'],
            'module.permissions.update' => ['label' => '更新權限管理', 'group' => '權限管理'],
            'module.vehicles.view' => ['label' => '查看車輛', 'group' => '車輛'],
            'module.vehicles.create' => ['label' => '建立車輛', 'group' => '車輛'],
            'module.vehicles.update' => ['label' => '更新車輛', 'group' => '車輛'],
            'module.vehicles.delete' => ['label' => '刪除車輛', 'group' => '車輛'],
            'module.vehicles.export' => ['label' => '匯出車輛', 'group' => '車輛'],
            'module.audit.view' => ['label' => '查看稽核紀錄', 'group' => '系統稽核'],
            'module.test-module.view' => ['label' => '查看測試模塊', 'group' => '測試'],
        ];

        // 技術註解：deprecated 相容層僅為過渡期保留，避免舊檢查點造成中斷；新功能不得再依賴舊命名。
        $deprecatedPermissionDefinitions = [
            'staff-permission.view' => ['label' => '[Deprecated] 查看員工權限管理', 'group' => 'Deprecated'],
            'staff-permission.update-role' => ['label' => '[Deprecated] 變更員工角色', 'group' => 'Deprecated'],
            'staff-permission.update-permission' => ['label' => '[Deprecated] 變更員工直接權限', 'group' => 'Deprecated'],
            'vehicle.view' => ['label' => '[Deprecated] 查看車輛', 'group' => 'Deprecated'],
            'module.vehicle.view' => ['label' => '[Deprecated] 查看車輛（單數 module）', 'group' => 'Deprecated'],
            'module.vehicle.create' => ['label' => '[Deprecated] 建立車輛（單數 module）', 'group' => 'Deprecated'],
            'module.vehicle.update' => ['label' => '[Deprecated] 更新車輛（單數 module）', 'group' => 'Deprecated'],
            'module.vehicle.delete' => ['label' => '[Deprecated] 刪除車輛（單數 module）', 'group' => 'Deprecated'],
            'module.vehicle.export' => ['label' => '[Deprecated] 匯出車輛（單數 module）', 'group' => 'Deprecated'],
        ];

        $permissionDefinitions = $permissionDefinitions + $deprecatedPermissionDefinitions;

        $permissions = collect($permissionDefinitions)
            ->filter(function (array $definition, string $name): bool {
                // 技術註解：統一命名必須符合 module.{module_key}.{action}，且 action 僅可來自白名單。
                if (!str_starts_with($name, 'module.')) {
                    return true;
                }

                $segments = explode('.', $name);
                $action = $segments[2] ?? null;

                return count($segments) === 3 && in_array($action, self::ACTION_WHITELIST, true);
            })
            ->mapWithKeys(fn (array $definition, string $name) => [
                $name => Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    [
                        'label' => $definition['label'],
                        'group' => $definition['group'],
                    ],
                ),
            ]);

        $roles = [
            'admin' => ['label' => '管理員'],
            'owner' => ['label' => '負責人'],
            'sales' => ['label' => '業務'],
            'accounting' => ['label' => '會計'],
            'inventory' => ['label' => '庫存'],
            'viewer' => ['label' => '檢視者'],
        ];

        $resolvedRoles = collect($roles)->mapWithKeys(fn (array $definition, string $name) => [
            $name => Role::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $definition['label']]
            ),
        ]);

        $modulePermissions = $permissions->filter(
            fn (Permission $permission) => str_starts_with($permission->name, 'module.')
        );

        $roleTemplates = [
            'admin' => [
                // 技術註解：admin 以正式權限清單為主來源，避免依賴 deprecated 權限造成能力缺漏與命名漂移。
                'module.dashboard.view',
                'module.staff.view',
                'module.staff.create',
                'module.staff.update',
                'module.staff.delete',
                'module.staff.export',
                'module.permissions.view',
                'module.permissions.update',
                'module.permissions.manage',
                'module.vehicles.view',
                'module.vehicles.create',
                'module.vehicles.update',
                'module.vehicles.delete',
                'module.vehicles.export',
                'module.audit.view',
                // 技術註解：以下 deprecated 權限僅為相容層保留，不作為主要授權來源。
                'staff-permission.view',
                'staff-permission.update-role',
                'staff-permission.update-permission',
            ],
            'owner' => [
                'module.dashboard.view',
                'module.staff.view',
                'module.permissions.view',
                'module.vehicles.view',
                'module.vehicles.create',
                'module.vehicles.update',
                'module.vehicles.delete',
                'module.vehicles.export',
            ],
            'sales' => [
                'module.dashboard.view',
                'module.vehicles.view',
                'module.vehicles.create',
                'module.vehicles.update',
            ],
            'accounting' => [
                'module.dashboard.view',
                'module.vehicles.view',
                'module.vehicles.export',
            ],
            'inventory' => [
                'module.dashboard.view',
                'module.vehicles.view',
                'module.vehicles.update',
            ],
            'viewer' => [
                'module.dashboard.view',
                'module.vehicles.view',
            ],
        ];

        foreach ($roleTemplates as $roleName => $permissionNames) {
            $resolvedRoles[$roleName]->syncPermissions(
                collect($permissionNames)
                    ->filter(fn (string $name) => $permissions->has($name))
                    ->map(fn (string $name) => $permissions[$name])
                    ->values()
            );
        }

        $adminUser = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                // 技術註解：User model 的 hashed cast 會統一處理測試密碼雜湊。
                'password' => 'password',
                'account_status' => 'active',
                // 技術註解：管理員必須綁定有效 tenant，避免建立車輛時傳入錯誤 company_id 造成跨租戶序號污染。
                'company_id' => $defaultCompany->id,
                'branch_id' => $defaultBranch->id,
            ],
        );

        $staffUser = User::updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff',
                // 技術註解：保持與 admin 相同測試密碼，降低驗證 RBAC 的操作成本。
                'password' => 'password',
                'account_status' => 'active',
                // 技術註解：員工帳號同樣需綁定 tenant，避免測試/示範資料觸發 tenant 邊界異常。
                'company_id' => $defaultCompany->id,
                'branch_id' => $defaultBranch->id,
            ],
        );

        $adminUser->syncRoles(['admin']);
        $staffUser->syncRoles(['viewer']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
