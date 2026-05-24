<?php

use App\Models\ActivityLog;
use App\Models\LoginLog;
use App\Models\Module;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Module::updateOrCreate(['key' => 'vehicles'], [
        'label' => '車輛管理',
        'section' => 'operations',
        'route_name' => 'employee-system.vehicles.index',
        'base_permission' => 'module.vehicles.view',
        'permission_prefix' => 'module.vehicles',
        'icon_key' => 'car',
        'sort_order' => 30,
        'is_enabled' => true,
        'is_active' => true,
        'active_patterns' => ['employee-system.vehicles.*'],
    ]);

    Module::updateOrCreate(['key' => 'audit'], [
        'label' => '稽核紀錄',
        'section' => 'administration',
        'route_name' => 'employee-system.audit.activity-logs',
        'base_permission' => 'module.audit.view',
        'permission_prefix' => 'module.audit',
        'icon_key' => 'ShieldCheck',
        'sort_order' => 40,
        'is_enabled' => true,
        'is_active' => true,
        'active_patterns' => ['employee-system.audit.*'],
    ]);

    Permission::findOrCreate('module.vehicles.view', 'web');
    Permission::findOrCreate('module.vehicles.create', 'web');
    Permission::findOrCreate('module.vehicles.update', 'web');
    Permission::findOrCreate('module.audit.view', 'web');
    Permission::findOrCreate('module.dashboard.view', 'web');
});

function ensureAuditTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(['id' => $companyId], [
        'name' => 'Company '.$companyId,
        'code' => 'AC'.$companyId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(['id' => $branchId], [
            'company_id' => $companyId,
            'name' => 'Branch '.$branchId,
            'code' => 'AB'.$companyId.'-'.$branchId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function makeAuditUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    ensureAuditTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'Audit User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

it('success login 記錄 login_logs success', function (): void {
    $user = makeAuditUser('audit-login-success@example.com');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('employee-system.overview', absolute: false));

    expect(LoginLog::query()->where('event', 'auth.login.success')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('failed login 記錄 failed 且不含 password', function (): void {
    $user = makeAuditUser('audit-login-failed@example.com');

    $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $log = LoginLog::query()->where('event', 'auth.login.failed')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log?->metadata)->toBeArray()
        ->and(array_key_exists('password', $log?->metadata ?? []))->toBeFalse();
});

it('inactive login 記錄 inactive', function (): void {
    $user = makeAuditUser('audit-login-inactive@example.com');
    $user->forceFill(['is_active' => false, 'account_status' => 'inactive'])->save();

    $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    expect(LoginLog::query()->where('event', 'auth.login.inactive')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('logout 記錄 logout', function (): void {
    $user = makeAuditUser('audit-logout@example.com');

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

    expect(LoginLog::query()->where('event', 'auth.logout')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('vehicle create 記錄 activity vehicle.created', function (): void {
    $user = makeAuditUser('audit-vehicle-create@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.create']);

    $this->actingAs($user)->post(route('employee-system.vehicles.store'), [
        'vin' => 'vin-audit-create-001',
        'license_plate' => 'AUD-1001',
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'variant' => '1.8',
        'model_year' => 2024,
        'exterior_color' => 'White',
        'interior_color' => 'Black',
        'odometer_km' => 123,
        'lifecycle_status' => 'in_stock',
        'internal_notes' => 'note',
    ])->assertRedirect();

    expect(ActivityLog::query()->where('event', 'vehicle.created')->exists())->toBeTrue();
});

it('vehicle update 記錄 activity vehicle.updated 且 old/new 正確', function (): void {
    $user = makeAuditUser('audit-vehicle-update@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.update']);

    $vehicle = Vehicle::create([
        'company_id' => 1,
        'branch_id' => 10,
        'stock_number' => 'VH-000001-0001',
        'vin' => 'VIN-AUDIT-UPD-001',
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'model_year' => 2022,
        'lifecycle_status' => 'in_stock',
    ]);

    $this->actingAs($user)->patch(route('employee-system.vehicles.update', $vehicle->id), [
        'vin' => 'vin-audit-upd-001',
        'license_plate' => null,
        'brand' => 'Toyota',
        'model' => 'Camry',
        'variant' => null,
        'model_year' => 2022,
        'exterior_color' => null,
        'interior_color' => null,
        'odometer_km' => null,
        'lifecycle_status' => 'reserved',
        'internal_notes' => null,
    ])->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $log = ActivityLog::query()->where('event', 'vehicle.updated')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log?->old_values['model'] ?? null)->toBe('Corolla')
        ->and($log?->new_values['model'] ?? null)->toBe('Camry')
        ->and($log?->old_values['lifecycle_status'] ?? null)->toBe('in_stock')
        ->and($log?->new_values['lifecycle_status'] ?? null)->toBe('reserved');
});

it('無 module.audit.view 不可看 audit logs', function (): void {
    $user = makeAuditUser('audit-no-permission@example.com');

    $this->actingAs($user)->get(route('employee-system.audit.activity-logs'))->assertForbidden();
    $this->actingAs($user)->get(route('employee-system.audit.login-logs'))->assertForbidden();
});

it('有權限 admin 可看 activity logs', function (): void {
    $user = makeAuditUser('audit-activity-allow@example.com');
    $user->givePermissionTo('module.audit.view');

    $this->actingAs($user)->get(route('employee-system.audit.activity-logs'))->assertOk();
});

it('有權限 admin 可看 login logs', function (): void {
    $user = makeAuditUser('audit-login-allow@example.com');
    $user->givePermissionTo('module.audit.view');

    $this->actingAs($user)->get(route('employee-system.audit.login-logs'))->assertOk();
});

it('audit 查詢不可跨 company 洩漏', function (): void {
    $user = makeAuditUser('audit-scope-self@example.com', 1, 10);
    $user->givePermissionTo('module.audit.view');

    ActivityLog::create(['company_id' => 1, 'event' => 'in.scope', 'action' => 'in.scope']);
    ActivityLog::create(['company_id' => 2, 'event' => 'out.scope', 'action' => 'out.scope']);
    ActivityLog::create(['company_id' => null, 'event' => 'system.scope', 'action' => 'system.scope']);

    $response = $this->actingAs($user)->get(route('employee-system.audit.activity-logs'));
    $response->assertOk();
    $response->assertSee('in.scope');
    $response->assertSee('system.scope');
    $response->assertDontSee('out.scope');
});

it('pagination 正常', function (): void {
    $user = makeAuditUser('audit-pagination@example.com', 1, 10);
    $user->givePermissionTo('module.audit.view');

    for ($i = 1; $i <= 21; $i++) {
        ActivityLog::create([
            'company_id' => 1,
            'event' => 'page.'.$i,
            'action' => 'page.'.$i,
        ]);
    }

    $this->actingAs($user)
        ->get(route('employee-system.audit.activity-logs'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('logs.per_page', 20)
            ->where('logs.total', 21)
        );
});

