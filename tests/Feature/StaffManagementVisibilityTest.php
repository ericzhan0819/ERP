<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffManagementVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_has_permissions_view_and_can_see_staff_management_sidebar_context(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);

        $admin = User::query()->where('phone', '0972111111')->firstOrFail();

        $this->assertTrue($admin->hasRole('Admin'));
        $this->assertTrue($admin->can('permissions.view'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.roles.0', 'Admin')
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('permissions.view'))
            );
    }
}
