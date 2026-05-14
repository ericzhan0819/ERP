<?php

use App\Models\Module;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PermissionService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('staff-permission.view', 'web');
    Permission::findOrCreate('staff-permission.update-permission', 'web');
    Permission::findOrCreate('staff-permission.update-permissions', 'web');
    Permission::findOrCreate('vehicle.view', 'web');

    Module::query()->updateOrCreate(
        ['key' => 'staff-permission'],
        [
            'label' => '員工權限',
            'route_name' => 'employee-system.staff-permissions.index',
            'base_permission' => 'staff-permission.view',
            'icon' => 'ShieldCheck',
            'sort_order' => 30,
            'is_active' => true,
        ]
    );

    Module::query()->updateOrCreate(
        ['key' => 'vehicle'],
        [
            'label' => '車輛管理',
            'route_name' => 'vehicle.index',
            'base_permission' => 'vehicle.view',
            'icon' => 'Car',
            'sort_order' => 10,
            'is_active' => true,
        ]
    );

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('staff', 'web');
});

it('允許具備 staff-permission.update-permissions 的管理者更新其他使用者 direct permissions', function (): void {
    $admin = User::create([
        'name' => 'Admin Permission Update',
        'email' => 'admin-perm-update@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-permissions');
    $admin->givePermissionTo('staff-permission.update-permission');

    $target = User::create([
        'name' => 'Target Permission Update',
        'email' => 'target-perm-update@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->patch(route('employee-system.staff-permissions.permissions.update', $target), [
        'permissions' => ['vehicle.view'],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success', '員工直接權限已更新');
    expect($target->fresh()->hasDirectPermission('vehicle.view'))->toBeTrue();
});

it('拒絕一般 staff 直接 PATCH direct permissions 並回傳 403', function (): void {
    $staff = User::create([
        'name' => 'Normal Staff',
        'email' => 'normal-staff-perm@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $staff->assignRole('staff');

    $target = User::create([
        'name' => 'Target Staff',
        'email' => 'target-staff-perm@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($staff)
        ->patch(route('employee-system.staff-permissions.permissions.update', $target), [
            'permissions' => ['vehicle.view'],
        ])
        ->assertForbidden();
});

it('拒絕使用者修改自己的 direct permissions', function (): void {
    $admin = User::create([
        'name' => 'Self Permission Admin',
        'email' => 'self-permission-admin@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-permissions');
    $admin->givePermissionTo('staff-permission.update-permission');

    $this->actingAs($admin)
        ->patch(route('employee-system.staff-permissions.permissions.update', $admin), [
            'permissions' => ['vehicle.view'],
        ])
        ->assertForbidden();

    expect($admin->fresh()->hasDirectPermission('vehicle.view'))->toBeFalse();
});

it('direct permission 更新成功後 Spatie direct permissions 關聯正確', function (): void {
    $admin = User::create([
        'name' => 'Admin Spatie Permission',
        'email' => 'admin-spatie-permission@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-permissions');
    $admin->givePermissionTo('staff-permission.update-permission');

    $target = User::create([
        'name' => 'Target Spatie Permission',
        'email' => 'target-spatie-permission@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($admin)->patch(route('employee-system.staff-permissions.permissions.update', $target), [
        'permissions' => ['vehicle.view'],
    ])->assertSessionHasNoErrors();

    expect($target->fresh()->permissions()->pluck('name')->all())->toBe(['vehicle.view']);
});

it('direct permission 更新成功時會寫入 audit log', function (): void {
    $admin = User::create([
        'name' => 'Admin Permission Audit',
        'email' => 'admin-permission-audit@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-permissions');
    $admin->givePermissionTo('staff-permission.update-permission');

    $target = User::create([
        'name' => 'Target Permission Audit',
        'email' => 'target-permission-audit@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('employee-system.staff-permissions.permissions.update', $target), [
            'permissions' => ['vehicle.view'],
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->id,
        'target_user_id' => $target->id,
        'action' => 'staff-permission.permissions.updated',
    ]);
});

it('當 direct permission 更新流程的 audit log 失敗時會回滾 direct permission 異動', function (): void {
    $admin = User::create([
        'name' => 'Admin Permission Rollback',
        'email' => 'admin-permission-rollback@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-permissions');
    $admin->givePermissionTo('staff-permission.update-permission');

    $target = User::create([
        'name' => 'Target Permission Rollback',
        'email' => 'target-permission-rollback@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    $this->mock(AuditLogService::class, function ($mock): void {
        // 技術註解：模擬稽核寫入失敗，驗證交易回滾可阻止 direct permission 部分寫入風險。
        $mock->shouldReceive('log')->andThrow(new RuntimeException('audit log failed'));
    });

    $this->actingAs($admin)
        ->patch(route('employee-system.staff-permissions.permissions.update', $target), [
            'permissions' => ['vehicle.view'],
        ])
        ->assertStatus(500);

    expect($target->fresh()->hasDirectPermission('vehicle.view'))->toBeFalse();

    $this->assertDatabaseMissing('activity_logs', [
        'user_id' => $admin->id,
        'target_user_id' => $target->id,
        'action' => 'staff-permission.permissions.updated',
    ]);
});

it('direct permission override 能讓使用者取得對應 module access', function (): void {
    $admin = User::create([
        'name' => 'Admin Permission Override',
        'email' => 'admin-permission-override@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-permissions');
    $admin->givePermissionTo('staff-permission.update-permission');

    $target = User::create([
        'name' => 'Target Permission Override',
        'email' => 'target-permission-override@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $target->assignRole('staff');

    $this->actingAs($admin)
        ->patch(route('employee-system.staff-permissions.permissions.update', $target), [
            'permissions' => ['vehicle.view'],
        ])
        ->assertSessionHasNoErrors();

    // 技術註解：先驗證 direct permission 已寫入，再驗證可映射為模組存取，避免僅測 UI 可見造成假陽性。
    $this->assertDatabaseHas('model_has_permissions', [
        'permission_id' => Permission::findByName('vehicle.view', 'web')->id,
        'model_type' => User::class,
        'model_id' => $target->id,
    ]);
    expect(app(PermissionService::class)->canAccessModule($target->fresh(), 'vehicle'))->toBeTrue();
});
