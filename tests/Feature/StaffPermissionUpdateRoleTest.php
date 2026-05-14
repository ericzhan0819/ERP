<?php

use App\Models\User;
use App\Models\Module;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('staff-permission.update-role', 'web');
    Permission::findOrCreate('staff-permission.view', 'web');

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

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('staff', 'web');
});

it('允許具備 staff-permission.update-role 的管理者更新其他使用者角色', function (): void {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-role-update@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-role');

    $target = User::create([
        'name' => 'Target',
        'email' => 'target-role-update@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $target->assignRole('staff');

    $response = $this->actingAs($admin)->patch(route('employee-system.staff-permissions.roles.update', $target), [
        'roles' => ['admin'],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success', '員工角色已更新');
    expect($target->fresh()->hasRole('admin'))->toBeTrue();
});

it('拒絕一般 staff 直接 PATCH 更新角色並回傳 403', function (): void {
    $staff = User::create([
        'name' => 'Staff',
        'email' => 'staff-role-update@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $staff->assignRole('staff');

    $target = User::create([
        'name' => 'Target 2',
        'email' => 'target2-role-update@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $target->assignRole('staff');

    $this->actingAs($staff)
        ->patch(route('employee-system.staff-permissions.roles.update', $target), [
            'roles' => ['admin'],
        ])
        ->assertForbidden();
});

it('拒絕使用者修改自己的角色', function (): void {
    $admin = User::create([
        'name' => 'Self Admin',
        'email' => 'self-admin-role-update@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-role');

    $this->actingAs($admin)
        ->patch(route('employee-system.staff-permissions.roles.update', $admin), [
            'roles' => ['staff'],
        ])
        ->assertForbidden();

    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});

it('更新後 Spatie role 關聯正確且維持單一角色語意', function (): void {
    $admin = User::create([
        'name' => 'Admin 2',
        'email' => 'admin2-role-update@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-role');

    $target = User::create([
        'name' => 'Target 3',
        'email' => 'target3-role-update@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $target->assignRole('staff');

    $this->actingAs($admin)->patch(route('employee-system.staff-permissions.roles.update', $target), [
        'roles' => ['admin'],
    ])->assertSessionHasNoErrors();

    $refreshed = $target->fresh();

    expect($refreshed->roles()->pluck('name')->all())->toBe(['admin']);
});

it('角色更新成功時會同步寫入 audit log 並保持資料一致', function (): void {
    $admin = User::create([
        'name' => 'Admin Audit Role',
        'email' => 'admin-audit-role@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-role');

    $target = User::create([
        'name' => 'Target Audit Role',
        'email' => 'target-audit-role@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $target->assignRole('staff');

    $this->actingAs($admin)
        ->patch(route('employee-system.staff-permissions.roles.update', $target), [
            'roles' => ['admin'],
        ])
        ->assertSessionHasNoErrors();

    expect($target->fresh()->hasRole('admin'))->toBeTrue();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->id,
        'target_user_id' => $target->id,
        'action' => 'staff-permission.role.updated',
    ]);
});

it('當角色更新流程的 audit log 失敗時會回滾角色異動', function (): void {
    $admin = User::create([
        'name' => 'Admin Rollback Role',
        'email' => 'admin-rollback-role@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-role');

    $target = User::create([
        'name' => 'Target Rollback Role',
        'email' => 'target-rollback-role@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $target->assignRole('staff');

    $this->mock(AuditLogService::class, function ($mock): void {
        // 技術註解：模擬稽核寫入失敗，驗證交易回滾可阻止 RBAC 部分寫入風險。
        $mock->shouldReceive('log')->andThrow(new RuntimeException('audit log failed'));
    });

    $this->actingAs($admin)
        ->patch(route('employee-system.staff-permissions.roles.update', $target), [
            'roles' => ['admin'],
        ])
        ->assertStatus(500);

    expect($target->fresh()->hasRole('staff'))->toBeTrue();
    expect($target->fresh()->hasRole('admin'))->toBeFalse();

    $this->assertDatabaseMissing('activity_logs', [
        'user_id' => $admin->id,
        'target_user_id' => $target->id,
        'action' => 'staff-permission.role.updated',
    ]);
});

it('允許具備 staff-permission.view 的使用者進入員工權限頁', function (): void {
    $user = User::create([
        'name' => 'View User',
        'email' => 'view-user@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $user->givePermissionTo('staff-permission.view');

    $this->actingAs($user)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertOk();
});

it('拒絕未具備 staff-permission.view 的已登入使用者直接開 URL 並回傳 403', function (): void {
    $user = User::create([
        'name' => 'No View User',
        'email' => 'no-view-user@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertForbidden();
});

it('未登入使用者存取員工權限頁會被導向 login', function (): void {
    $this->get(route('employee-system.staff-permissions.index'))
        ->assertRedirect(route('login'));
});
