<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the application's RBAC foundation.
     */
    public function run(): void
    {
        // 技術註解：重建最小 RBAC 快取，確保 fresh seed 後權限判斷立即一致。
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = [
            'dashboard' => [
                'label' => '總覽',
                'route_name' => 'employee-system.overview',
                'base_permission' => 'module.dashboard.view',
                'icon' => 'LayoutDashboard',
                'sort_order' => 10,
                'is_active' => true,
            ],
            'test-module' => [
                'label' => '測試模塊',
                'route_name' => 'employee-system.test-module',
                'base_permission' => 'module.test-module.view',
                'icon' => 'FlaskConical',
                'sort_order' => 90,
                'is_active' => true,
            ],
            'staff-permission' => [
                'label' => '員工權限管理',
                'route_name' => 'employee-system.staff-permissions.index',
                'base_permission' => 'staff-permission.view',
                'icon' => 'ShieldCheck',
                'sort_order' => 20,
                'is_active' => true,
            ],
        ];

        foreach ($modules as $key => $module) {
            // 技術註解：updateOrCreate 讓模組註冊可重跑，並保持 seed 結果單一真實來源。
            Module::updateOrCreate(['key' => $key], $module);
        }

        $permissionDefinitions = [
            'module.dashboard.view' => [
                'label' => '查看總覽',
                'group' => '系統',
            ],
            'module.test-module.view' => [
                'label' => '查看測試模塊',
                'group' => '測試',
            ],
            'staff-permission.view' => [
                'label' => '查看員工權限管理',
                'group' => '權限管理',
            ],
            'staff-permission.update-role' => [
                'label' => '變更員工角色',
                'group' => '權限管理',
            ],
            'staff-permission.update-permission' => [
                'label' => '變更員工直接權限',
                'group' => '權限管理',
            ],
        ];

        $permissions = collect($permissionDefinitions)
            ->mapWithKeys(fn (array $definition, string $name) => [
                $name => Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    [
                        'label' => $definition['label'],
                        'group' => $definition['group'],
                    ],
                ),
            ]);

        $admin = Role::updateOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['label' => '管理員']
        );

        $staff = Role::updateOrCreate(
            ['name' => 'staff', 'guard_name' => 'web'],
            ['label' => '員工']
        );  

        $admin->syncPermissions($permissions->values());
        $staff->syncPermissions([$permissions['module.dashboard.view']]);

        $adminUser = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                // 技術註解：User model 的 hashed cast 會統一處理測試密碼雜湊。
                'password' => 'password',
                'account_status' => 'active',
            ],
        );

        $staffUser = User::updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff',
                // 技術註解：保持與 admin 相同測試密碼，降低驗證 RBAC 的操作成本。
                'password' => 'password',
                'account_status' => 'active',
            ],
        );

        $adminUser->syncRoles(['admin']);
        $staffUser->syncRoles(['staff']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
