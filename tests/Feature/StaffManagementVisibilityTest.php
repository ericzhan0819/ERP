<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StaffManagementVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_staff_management_page(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);

        $admin = User::query()->where('phone', '0972111111')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('staff-management.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StaffManagement/Index')
            );
    }

    public function test_non_admin_cannot_access_staff_management_page(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);

        $staff = User::query()->where('phone', '0972333333')->firstOrFail();

        $this->actingAs($staff)
            ->get(route('staff-management.index'))
            ->assertForbidden();
    }

    public function test_admin_can_update_target_user_role_and_whitelisted_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);

        Permission::findOrCreate('module.accounting', 'web');
        Permission::findOrCreate('module.crm', 'web');
        Permission::findOrCreate('widget.financial_health', 'web');

        $admin = User::query()->where('phone', '0972111111')->firstOrFail();
        $target = User::query()->where('phone', '0972333333')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('staff-management.update', $target), [
                'role' => 'Manager',
                'permissions' => ['module.accounting', 'widget.financial_health'],
            ])
            ->assertOk();

        $target->refresh();
        $this->assertTrue($target->hasRole('Manager'));
        $this->assertTrue($target->hasPermissionTo('module.accounting'));
        $this->assertTrue($target->hasPermissionTo('widget.financial_health'));

        $this->actingAs($admin)
            ->get(route('staff-management.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('staff.1.roles.0', 'Manager')
            );
    }

    public function test_non_admin_cannot_update_staff_role(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);

        $staff = User::query()->where('phone', '0972333333')->firstOrFail();
        $target = User::query()->where('phone', '0972222222')->firstOrFail();

        $this->actingAs($staff)
            ->patch(route('staff-management.update', $target), [
                'role' => 'Manager',
                'permissions' => [],
            ])
            ->assertForbidden();
    }
}
