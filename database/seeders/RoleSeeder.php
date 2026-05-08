<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 清除 Spatie Permission 快取，避免角色建立後讀取舊資料
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 建立權限：供前端側欄「權限管理」顯示判斷使用
        $permissionsView = Permission::findOrCreate('permissions.view', 'web');

        // 初始化系統角色
        $adminRole = Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Manager', 'web');
        Role::findOrCreate('Staff', 'web');

        // Admin 需具備「權限管理」可見權限
        $adminRole->givePermissionTo($permissionsView);
    }
}
