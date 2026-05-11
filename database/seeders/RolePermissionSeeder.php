<?php

namespace Database\Seeders;

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

        $dashboardPermission = Permission::firstOrCreate(['name' => 'module.dashboard.view', 'guard_name' => 'web']);
        $testModulePermission = Permission::firstOrCreate(['name' => 'module.test-module.view', 'guard_name' => 'web']);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $admin->syncPermissions([$dashboardPermission, $testModulePermission]);
        $staff->syncPermissions([$dashboardPermission]);

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
