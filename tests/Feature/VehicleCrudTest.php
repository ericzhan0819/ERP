<?php

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

    // 技術註解：車輛路由受 module.access:vehicles 保護，測試需先建立模組註冊資料以避免門禁前置失敗。
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
    Permission::findOrCreate('module.vehicles.create', 'web');
    Permission::findOrCreate('module.vehicles.update', 'web');
});

/**
 * 技術註解：集中建立最小測試使用者，固定 tenant 邊界以精準驗證 scope 與 RBAC 行為。
 */
function makeVehicleCrudUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    ensureTenantBoundaryRows($companyId, $branchId);

    return User::create([
        'name' => 'Vehicle CRUD User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

/**
 * 技術註解：集中建立最小車輛資料，避免每個案例重複組裝資料而模糊測試意圖。
 */
function makeVehicleCrudRecord(int $companyId, int $branchId, string $stock, string $vin): Vehicle
{
    // 技術註解：Vehicle 雖未強制 FK，但先補齊 tenant 主資料可維持測試資料語意完整且降低未來 schema 調整破壞測試風險。
    ensureTenantBoundaryRows($companyId, $branchId);

    return Vehicle::create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'stock_number' => $stock,
        'vin' => $vin,
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'model_year' => 2022,
        'lifecycle_status' => 'in_stock',
    ]);
}

/**
 * @return array<string, mixed>
 */
function validVehiclePayload(array $overrides = []): array
{
    return array_merge([
        'stock_number' => 'STK-NEW-001',
        'vin' => 'vin-new-001',
        'license_plate' => 'ABC-1234',
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'variant' => '1.8',
        'model_year' => 2023,
        'exterior_color' => 'White',
        'interior_color' => 'Black',
        'odometer_km' => 1000,
        'lifecycle_status' => 'in_stock',
        'internal_notes' => '測試資料',
    ], $overrides);
}

/**
 * 技術註解：users table 對 company_id/branch_id 有 FK 約束，測試需建立最小 tenant 主資料以反映正式環境完整性。
 */
function ensureTenantBoundaryRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'Company '.$companyId,
            'code' => 'C'.$companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            [
                'company_id' => $companyId,
                'name' => 'Branch '.$branchId,
                'code' => 'B'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

it('有 module.vehicles.view 權限可進入 vehicles index', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-index-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.index'))
        ->assertOk();
});

it('有 module.vehicles.view 權限可查看同 company/branch vehicle show', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-show-same-scope@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = makeVehicleCrudRecord(1, 10, 'STK-SHOW-001', 'vin-show-001');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertOk();
});

it('有 module.vehicles.create 權限可 store vehicle', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload())
        ->assertRedirect();

    $this->assertDatabaseHas('vehicles', [
        'stock_number' => 'STK-NEW-001',
        'company_id' => 1,
        'branch_id' => 10,
    ]);
});

it('store 時 request 傳入 company_id 仍強制使用登入者 company_id', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-company-locked@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload([
            'stock_number' => 'STK-NEW-002',
            'vin' => 'vin-new-002',
            'company_id' => 2,
        ]))
        ->assertRedirect();

    $vehicle = Vehicle::where('stock_number', 'STK-NEW-002')->firstOrFail();
    expect($vehicle->company_id)->toBe(1);
});

it('store 時 request 傳入 branch_id 仍強制使用登入者 branch_id', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-branch-locked@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload([
            'stock_number' => 'STK-NEW-003',
            'vin' => 'vin-new-003',
            'branch_id' => 99,
        ]))
        ->assertRedirect();

    $vehicle = Vehicle::where('stock_number', 'STK-NEW-003')->firstOrFail();
    expect($vehicle->branch_id)->toBe(10);
});

it('store 時 created_by 與 updated_by 必須是登入者 id', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-auditor@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload([
            'stock_number' => 'STK-NEW-004',
            'vin' => 'vin-new-004',
        ]))
        ->assertRedirect();

    $vehicle = Vehicle::where('stock_number', 'STK-NEW-004')->firstOrFail();
    expect($vehicle->created_by)->toBe($user->id)
        ->and($vehicle->updated_by)->toBe($user->id);
});

it('有 module.vehicles.update 權限可 update 同 company/branch vehicle', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-update-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleCrudRecord(1, 10, 'STK-UPD-001', 'vin-upd-001');

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.update', $vehicle->id), validVehiclePayload([
            'stock_number' => 'STK-UPD-001',
            'vin' => 'vin-updated-001',
            'model' => 'Camry',
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $vehicle->refresh();
    expect($vehicle->model)->toBe('Camry')
        ->and($vehicle->vin)->toBe('VIN-UPDATED-001');
});

it('update 時不能修改 company_id / branch_id', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-update-tenant-locked@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleCrudRecord(1, 10, 'STK-UPD-002', 'vin-upd-002');

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.update', $vehicle->id), validVehiclePayload([
            'stock_number' => 'STK-UPD-002',
            'vin' => 'vin-updated-002',
            'company_id' => 2,
            'branch_id' => 99,
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $vehicle->refresh();
    expect($vehicle->company_id)->toBe(1)
        ->and($vehicle->branch_id)->toBe(10);
});

it('update 時 updated_by 必須更新為登入者 id', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-update-auditor@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleCrudRecord(1, 10, 'STK-UPD-003', 'vin-upd-003');

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.update', $vehicle->id), validVehiclePayload([
            'stock_number' => 'STK-UPD-003',
            'vin' => 'vin-updated-003',
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $vehicle->refresh();
    expect($vehicle->updated_by)->toBe($user->id);
});

it('沒有 module.vehicles.view 權限不能進入 vehicles index/show（scope 內）', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-no-view@example.com');
    $vehicle = makeVehicleCrudRecord(1, 10, 'STK-NO-VIEW-001', 'vin-no-view-001');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertForbidden();
});

it('沒有 module.vehicles.create 權限不能 store（scope 內）', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-no-create@example.com');
    $user->givePermissionTo('module.vehicles.view');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload())
        ->assertForbidden();
});

it('沒有 module.vehicles.update 權限不能 update（scope 內）', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-no-update@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = makeVehicleCrudRecord(1, 10, 'STK-NO-UPD-001', 'vin-no-upd-001');

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.update', $vehicle->id), validVehiclePayload([
            'stock_number' => 'STK-NO-UPD-001',
            'vin' => 'vin-no-upd-001',
        ]))
        ->assertForbidden();
});

it('跨 company 的 vehicle show/edit/update 必須 404', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-cross-company@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleCrudRecord(2, 10, 'STK-XCOMP-001', 'vin-xcomp-001');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.edit', $vehicle->id))
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.update', $vehicle->id), validVehiclePayload([
            'stock_number' => 'STK-XCOMP-001',
            'vin' => 'vin-xcomp-001',
        ]))
        ->assertNotFound();
});

it('跨 branch 的 vehicle show/edit/update 必須 404', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-cross-branch@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleCrudRecord(1, 99, 'STK-XBRANCH-001', 'vin-xbranch-001');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.edit', $vehicle->id))
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.update', $vehicle->id), validVehiclePayload([
            'stock_number' => 'STK-XBRANCH-001',
            'vin' => 'vin-xbranch-001',
        ]))
        ->assertNotFound();
});
