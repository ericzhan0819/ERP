<?php

use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('staff-permission.view', 'web');
    Permission::findOrCreate('staff-permission.update-permission', 'web');
    Permission::findOrCreate('staff-permission.update-role', 'web');
    Permission::findOrCreate('module.permissions.view', 'web');
    Permission::findOrCreate('module.vehicles.view', 'web');

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

it('admin 可查看角色權限頁', function (): void {
    $admin = User::create([
        'name' => 'Admin Role Matrix',
        'email' => 'admin-role-matrix@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.view');

    $this->actingAs($admin)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertOk();
});

it('無權限者不可查看角色權限頁', function (): void {
    $user = User::create([
        'name' => 'No Permission User',
        'email' => 'no-perm-role-matrix@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertForbidden();
});

it('admin 可更新角色 permissions', function (): void {
    $admin = User::create([
        'name' => 'Admin Update Matrix',
        'email' => 'admin-update-role-matrix@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-permission');

    $role = Role::findByName('staff', 'web');

    $this->actingAs($admin)
        ->patch(route('employee-system.staff-permissions.roles.permissions.update', $role), [
            'permissions' => ['module.vehicles.view'],
        ])
        ->assertSessionHasNoErrors();

    expect($role->fresh()->hasPermissionTo('module.vehicles.view'))->toBeTrue();
});

it('無 staff-permission.update-permission 者不可更新角色 permissions', function (): void {
    $staff = User::create([
        'name' => 'Staff No Update Matrix',
        'email' => 'staff-no-update-role-matrix@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $staff->assignRole('staff');

    $role = Role::findByName('staff', 'web');

    $this->actingAs($staff)
        ->patch(route('employee-system.staff-permissions.roles.permissions.update', $role), [
            'permissions' => ['module.vehicles.view'],
        ])
        ->assertForbidden();
});

it('permissionMatrix 不含 deprecated module.vehicle 群組且包含正式 vehicles 群組', function (): void {
    $this->seed(RolePermissionSeeder::class);
    Permission::query()->where('name', 'module.vehicles.export')->delete();

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            // 技術註解：使用 missing 斷言可直接驗證 deprecated 單數群組已自矩陣移除，避免前端誤用舊權限命名。
            ->missing('permissionMatrix.vehicle')
            ->has('permissionMatrix.vehicles')
            ->where('permissionMatrix.vehicles.actions.view.permission', 'module.vehicles.view')
            ->missing('permissionMatrix.vehicles.actions.export')
        );
});

it('可新增角色且可刪除未綁定使用者的非系統角色', function (): void {
    $admin = User::create([
        'name' => 'Admin Role CRUD',
        'email' => 'admin-role-crud@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('staff-permission.update-role');

    $this->actingAs($admin)
        ->post(route('employee-system.staff-permissions.roles.store'), [
            'name' => 'qa-role',
            'label' => 'QA 角色',
        ])
        ->assertSessionHasNoErrors();

    $role = Role::query()->where('name', 'qa-role')->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('employee-system.staff-permissions.roles.destroy', $role))
        ->assertSessionHasNoErrors();

    expect(Role::query()->where('name', 'qa-role')->exists())->toBeFalse();
});

it('admin rolePermissionMap 含 module.vehicles create update delete export 正式權限', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    $adminRoleId = (string) Role::findByName('admin', 'web')->id;

    $this->actingAs($admin)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rolePermissionMap.'.$adminRoleId, function ($permissions): bool {
                $permissions = is_array($permissions) ? $permissions : $permissions->all();
                $permissionSet = array_flip($permissions);

                return isset($permissionSet['module.vehicles.create'])
                    && isset($permissionSet['module.vehicles.update'])
                    && isset($permissionSet['module.vehicles.delete'])
                    && isset($permissionSet['module.vehicles.export']);
            })
        );
});

it('更新角色權限不依賴 module.vehicle deprecated 權限', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    $role = Role::findByName('staff', 'web');

    $this->actingAs($admin)
        ->patch(route('employee-system.staff-permissions.roles.permissions.update', $role), [
            'permissions' => [
                'module.vehicles.view',
                'module.vehicles.create',
                'module.vehicles.update',
                'module.vehicles.delete',
                'module.vehicles.export',
            ],
        ])
        ->assertSessionHasNoErrors();

    $freshRole = $role->fresh();

    expect($freshRole->hasPermissionTo('module.vehicles.view'))->toBeTrue()
        ->and($freshRole->hasPermissionTo('module.vehicles.create'))->toBeTrue()
        ->and($freshRole->hasPermissionTo('module.vehicles.update'))->toBeTrue()
        ->and($freshRole->hasPermissionTo('module.vehicles.delete'))->toBeTrue()
        ->and($freshRole->hasPermissionTo('module.vehicles.export'))->toBeTrue()
        ->and($freshRole->hasPermissionTo('module.vehicle.view'))->toBeFalse();
});
