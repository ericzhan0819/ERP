<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 技術註解：路由會先經過 module.access:vehicles，測試需顯式建立 modules registry 記錄，避免因缺模組資料而誤判為授權失敗。
    Module::updateOrCreate(
        ['key' => 'vehicles'],
        [
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
        ]
    );

    Permission::findOrCreate('module.vehicles.view', 'web');
});

/**
 * 技術註解：集中建立 tenant 資料，確保所有讀取授權測試都以真實 company/branch 邊界驗證，避免 IDOR 風險被測試假象掩蓋。
 */
function createVehicleReadUser(string $email, int $companyId, ?int $branchId): User
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        ['name' => 'Company '.$companyId, 'code' => 'C'.$companyId, 'created_at' => now(), 'updated_at' => now()]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            ['company_id' => $companyId, 'name' => 'Branch '.$branchId, 'code' => 'B'.$branchId, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    return User::create([
        'name' => 'Vehicle Reader',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function createVehicleRecord(int $companyId, int $branchId, string $stock, string $vin): Vehicle
{
    return Vehicle::create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'stock_number' => $stock,
        'vin' => $vin,
        'brand' => 'Toyota',
        'model' => 'Yaris',
        'model_year' => 2021,
        'lifecycle_status' => 'in_stock',
    ]);
}

it('same company visible', function (): void {
    $user = createVehicleReadUser('vehicle-read-same-company@example.com', 1, 10);
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = createVehicleRecord(1, 10, 'SAME-001', 'same-001');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertOk();
});

it('cross-company 404', function (): void {
    $user = createVehicleReadUser('vehicle-read-cross-company@example.com', 1, 10);
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = createVehicleRecord(2, 10, 'CROSS-COMPANY-001', 'cross-company-001');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertNotFound();
});

it('cross-branch denied', function (): void {
    $user = createVehicleReadUser('vehicle-read-cross-branch@example.com', 1, 10);
    $user->givePermissionTo('module.vehicles.view');
    createVehicleRecord(1, 10, 'BRANCH-SELF-001', 'branch-self-001');
    createVehicleRecord(1, 20, 'BRANCH-OTHER-001', 'branch-other-001');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.index'))
        ->assertOk()
        ->assertSee('BRANCH-SELF-001')
        ->assertDontSee('BRANCH-OTHER-001');
});

it('company-level cross-branch allowed', function (): void {
    $user = createVehicleReadUser('vehicle-read-company-level@example.com', 1, null);
    $user->givePermissionTo('module.vehicles.view');
    createVehicleRecord(1, 10, 'COMPANY-L1', 'company-l1');
    createVehicleRecord(1, 20, 'COMPANY-L2', 'company-l2');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.index'))
        ->assertOk()
        ->assertSee('COMPANY-L1')
        ->assertSee('COMPANY-L2');
});

it('no permission denied', function (): void {
    $user = createVehicleReadUser('vehicle-read-no-permission@example.com', 1, 10);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.index'))
        ->assertForbidden();
});

it('direct permission override allowed', function (): void {
    $user = createVehicleReadUser('vehicle-read-direct-permission@example.com', 1, 10);
    // 技術註解：此案例驗證 direct permission 不依賴角色也可授權通過，避免誤把 role 當唯一授權來源。
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = createVehicleRecord(1, 10, 'DIRECT-001', 'direct-001');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertOk();
});

it('soft deleted hidden', function (): void {
    $user = createVehicleReadUser('vehicle-read-soft-deleted@example.com', 1, 10);
    $user->givePermissionTo('module.vehicles.view');
    $visible = createVehicleRecord(1, 10, 'VISIBLE-001', 'visible-001');
    $deleted = createVehicleRecord(1, 10, 'DELETED-001', 'deleted-001');
    $deleted->delete();

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.index'))
        ->assertOk()
        ->assertSee($visible->stock_number)
        ->assertDontSee($deleted->stock_number);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $deleted->id))
        ->assertNotFound();
});
