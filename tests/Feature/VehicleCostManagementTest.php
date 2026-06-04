<?php

use App\Models\Module;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCost;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Module::updateOrCreate(['key' => 'vehicle-costs'], [
        'label' => '車輛成本管理',
        'section' => 'operations',
        'route_name' => 'employee-system.vehicle-costs.index',
        'base_permission' => 'module.vehicles.costs.view',
        'permission_prefix' => 'module.vehicles.costs',
        'is_enabled' => true,
        'is_active' => true,
    ]);

    foreach (['module.vehicles.costs.view', 'module.vehicles.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

function vcmTenant(int $companyId = 1, int $branchId = 10): void
{
    DB::table('companies')->updateOrInsert(['id' => $companyId], ['name' => 'VCM Co '.$companyId, 'code' => 'VCM'.$companyId, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('branches')->updateOrInsert(['id' => $branchId], ['company_id' => $companyId, 'name' => 'VCM Br '.$branchId, 'code' => 'VB'.$branchId, 'created_at' => now(), 'updated_at' => now()]);
}

function vcmUser(string $email, int $companyId = 1, int $branchId = 10): User
{
    vcmTenant($companyId, $branchId);

    return User::create(['name' => 'VCM User', 'email' => $email, 'password' => 'password', 'account_status' => 'active', 'is_active' => true, 'company_id' => $companyId, 'branch_id' => $branchId]);
}

function vcmVehicle(User $user, string $stock, string $plate = 'VCM-001', string $brand = 'Toyota', string $model = 'Altis'): Vehicle
{
    return Vehicle::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'stock_number' => $stock, 'vin' => $stock.'VIN', 'brand' => $brand, 'model' => $model, 'model_year' => 2024, 'license_plate' => $plate, 'lifecycle_status' => 'in_stock']);
}

function vcmCost(Vehicle $vehicle, User $user, array $overrides = []): VehicleCost
{
    return VehicleCost::create(array_merge([
        'company_id' => $vehicle->company_id,
        'branch_id' => $vehicle->branch_id,
        'vehicle_id' => $vehicle->id,
        'cost_type' => 'repair',
        'description' => '引擎整備',
        'amount' => 1000,
        'cost_date' => '2026-06-01',
        'vendor_name' => '安心修車廠',
        'payment_status' => 'unpaid',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

it('admin 或有成本檢視權限者可以進入 vehicle costs index', function (): void {
    $user = vcmUser('vcm-view@example.com');
    $user->givePermissionTo('module.vehicles.costs.view');
    $cost = vcmCost(vcmVehicle($user, 'VCM-OK'), $user);

    $this->actingAs($user)
        ->get(route('employee-system.vehicle-costs.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('VehicleCosts/Index')
            ->where('costs.data.0.id', $cost->id)
            ->where('costs.data.0.vehicle.stock_number', 'VCM-OK')
        );
});

it('沒有成本檢視權限者不可進入 index', function (): void {
    $user = vcmUser('vcm-deny@example.com');

    $this->actingAs($user)
        ->get(route('employee-system.vehicle-costs.index'))
        ->assertForbidden();
});

it('index 只顯示同 company 與 branch 的 vehicle costs', function (): void {
    $user = vcmUser('vcm-scope@example.com');
    $user->givePermissionTo('module.vehicles.costs.view');
    $own = vcmCost(vcmVehicle($user, 'VCM-OWN'), $user);
    $sameCompanyOtherBranchUser = vcmUser('vcm-other-branch@example.com', 1, 11);
    vcmCost(vcmVehicle($sameCompanyOtherBranchUser, 'VCM-BRANCH'), $sameCompanyOtherBranchUser);

    $this->actingAs($user)
        ->get(route('employee-system.vehicle-costs.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('costs.data.0.id', $own->id)
            ->missing('costs.data.1')
        );
});

it('index 不顯示跨 tenant vehicle costs', function (): void {
    $user = vcmUser('vcm-tenant@example.com');
    $user->givePermissionTo('module.vehicles.costs.view');
    $own = vcmCost(vcmVehicle($user, 'VCM-TENANT-OWN'), $user);
    $crossUser = vcmUser('vcm-cross@example.com', 2, 20);
    vcmCost(vcmVehicle($crossUser, 'VCM-CROSS'), $crossUser);

    $this->actingAs($user)
        ->get(route('employee-system.vehicle-costs.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('costs.data.0.id', $own->id)
            ->missing('costs.data.1')
        );
});

it('q 可搜尋 stock number、license plate、vendor 與 description', function (): void {
    $user = vcmUser('vcm-search@example.com');
    $user->givePermissionTo('module.vehicles.costs.view');
    $vehicle = vcmVehicle($user, 'FIND-STOCK', 'FIND-PLATE', 'Honda', 'Fit');
    $cost = vcmCost($vehicle, $user, ['description' => '特殊鈑金', 'vendor_name' => '亮晶晶美容']);
    vcmCost(vcmVehicle($user, 'NO-MATCH'), $user, ['description' => '一般維修', 'vendor_name' => '一般廠商']);

    foreach (['FIND-STOCK', 'FIND-PLATE', '亮晶晶', '特殊鈑金'] as $keyword) {
        $this->actingAs($user)
            ->get(route('employee-system.vehicle-costs.index', ['q' => $keyword]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('costs.data.0.id', $cost->id)->missing('costs.data.1'));
    }
});

it('cost_type 與 payment_status filter 可正常篩選', function (): void {
    $user = vcmUser('vcm-filter@example.com');
    $user->givePermissionTo('module.vehicles.costs.view');
    $repair = vcmCost(vcmVehicle($user, 'VCM-REPAIR'), $user, ['cost_type' => 'repair', 'payment_status' => 'paid', 'amount' => 200]);
    vcmCost(vcmVehicle($user, 'VCM-TAX'), $user, ['cost_type' => 'tax', 'payment_status' => 'unpaid', 'amount' => 300]);

    $this->actingAs($user)
        ->get(route('employee-system.vehicle-costs.index', ['cost_type' => 'repair', 'payment_status' => 'paid']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('costs.data.0.id', $repair->id)->missing('costs.data.1'));
});

it('summary 只計算目前 tenant 與 filter 範圍內的成本', function (): void {
    $user = vcmUser('vcm-summary@example.com');
    $user->givePermissionTo('module.vehicles.costs.view');
    vcmCost(vcmVehicle($user, 'VCM-S1'), $user, ['cost_type' => 'repair', 'payment_status' => 'paid', 'amount' => 100]);
    vcmCost(vcmVehicle($user, 'VCM-S2'), $user, ['cost_type' => 'repair', 'payment_status' => 'unpaid', 'amount' => 200]);
    vcmCost(vcmVehicle($user, 'VCM-S3'), $user, ['cost_type' => 'tax', 'payment_status' => 'unpaid', 'amount' => 500]);
    $crossUser = vcmUser('vcm-summary-cross@example.com', 2, 20);
    vcmCost(vcmVehicle($crossUser, 'VCM-SX'), $crossUser, ['cost_type' => 'repair', 'payment_status' => 'paid', 'amount' => 900]);

    $this->actingAs($user)
        ->get(route('employee-system.vehicle-costs.index', ['cost_type' => 'repair']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.total_amount', '300')
            ->where('summary.paid_amount', '100')
            ->where('summary.unpaid_amount', '200')
            ->where('summary.count', 2)
        );
});

it('response payload 不包含 profit 或 tenant 敏感欄位', function (): void {
    $user = vcmUser('vcm-payload@example.com');
    $user->givePermissionTo('module.vehicles.costs.view');
    vcmCost(vcmVehicle($user, 'VCM-PAYLOAD'), $user);

    $this->actingAs($user)
        ->get(route('employee-system.vehicle-costs.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('costs.data.0.company_id')
            ->missing('costs.data.0.branch_id')
            ->missing('costs.data.0.internal_notes')
            ->missing('costs.data.0.profit')
            ->missing('costs.data.0.gross_margin')
            ->missing('costs.data.0.margin')
            ->missing('costs.data.0.net_profit')
            ->missing('costs.data.0.vehicle.company_id')
            ->missing('costs.data.0.vehicle.branch_id')
        );
});

it('module registry seed 後有 vehicle-costs module 且沿用既有 base permission', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $module = Module::query()->where('key', 'vehicle-costs')->firstOrFail();

    expect($module->label)->toBe('車輛成本管理')
        ->and($module->route_name)->toBe('employee-system.vehicle-costs.index')
        ->and($module->base_permission)->toBe('module.vehicles.costs.view')
        ->and($module->permission_prefix)->toBe('module.vehicles.costs');
});