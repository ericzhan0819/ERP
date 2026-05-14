<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('module.vehicle.view', 'web');
    Permission::findOrCreate('module.vehicle.update', 'web');
});

/**
 * 技術註解：集中建立使用者，避免測試重複並確保 tenant 邊界欄位一致。
 */
function makeVehicleUser(string $email, int $companyId, ?int $branchId): User
{
    // 技術註解：先建立對應 company/branch，避免 FK 阻擋而使 policy 測試偏離授權主題。
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
                'code' => 'B'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    return User::create([
        'name' => 'Vehicle User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        // 技術註解：改為寫入真實 users tenant 欄位，避免記憶體注入造成測試與正式行為不一致。
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

/**
 * 技術註解：集中建立車輛，讓每個案例只關注授權條件而非資料組裝細節。
 */
function makeVehicle(int $companyId, int $branchId, string $stock, string $vin): Vehicle
{
    return Vehicle::create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'stock_number' => $stock,
        'vin' => $vin,
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'model_year' => 2020,
        'lifecycle_status' => 'in_stock',
    ]);
}

it('同 company 可 view vehicle', function (): void {
    $user = makeVehicleUser('vehicle-view-same-company@example.com', 1, 10);
    $user->givePermissionTo('module.vehicle.view');
    $vehicle = makeVehicle(1, 10, 'STK-001', 'vin-001');

    expect($user->can('view', $vehicle))->toBeTrue();
});

it('不同 company 不可 view vehicle', function (): void {
    $user = makeVehicleUser('vehicle-view-cross-company@example.com', 1, 10);
    $user->givePermissionTo('module.vehicle.view');
    $vehicle = makeVehicle(2, 10, 'STK-002', 'vin-002');

    expect($user->can('view', $vehicle))->toBeFalse();
});

it('branch user 不可 view 其他 branch vehicle', function (): void {
    $user = makeVehicleUser('vehicle-view-cross-branch@example.com', 1, 10);
    $user->givePermissionTo('module.vehicle.view');
    $vehicle = makeVehicle(1, 20, 'STK-003', 'vin-003');

    expect($user->can('view', $vehicle))->toBeFalse();
});

it('admin 可 view 同 company 不同 branch', function (): void {
    // 技術註解：branch_id 為 null 代表公司層級使用者，可跨分店但不得跨公司。
    $admin = makeVehicleUser('vehicle-admin-view@example.com', 1, null);
    $admin->givePermissionTo('module.vehicle.view');
    $vehicle = makeVehicle(1, 99, 'STK-004', 'vin-004');

    expect($admin->can('view', $vehicle))->toBeTrue();
});

it('無 module.vehicle.view 權限者 denied', function (): void {
    $user = makeVehicleUser('vehicle-no-view-permission@example.com', 1, 10);
    $vehicle = makeVehicle(1, 10, 'STK-005', 'vin-005');

    expect($user->can('view', $vehicle))->toBeFalse();
});

it('direct permission override 可 view', function (): void {
    // 技術註解：驗證 direct permission 可直接通過 policy 權限檢查，不依賴角色綁定。
    $user = makeVehicleUser('vehicle-direct-permission-view@example.com', 1, 10);
    $user->givePermissionTo('module.vehicle.view');
    $vehicle = makeVehicle(1, 10, 'STK-006', 'vin-006');

    expect($user->can('view', $vehicle))->toBeTrue();
});

it('update permission 正常運作', function (): void {
    $user = makeVehicleUser('vehicle-update-same-tenant@example.com', 1, 10);
    $user->givePermissionTo('module.vehicle.update');
    $vehicle = makeVehicle(1, 10, 'STK-007', 'vin-007');

    expect($user->can('update', $vehicle))->toBeTrue();
});

it('cross-company update denied', function (): void {
    $user = makeVehicleUser('vehicle-update-cross-company@example.com', 1, 10);
    $user->givePermissionTo('module.vehicle.update');
    $vehicle = makeVehicle(2, 10, 'STK-008', 'vin-008');

    expect($user->can('update', $vehicle))->toBeFalse();
});

it('soft deleted vehicle 不可被一般查詢取得', function (): void {
    $vehicle = makeVehicle(1, 10, 'STK-009', 'vin-009');
    $vehicle->delete();

    // 技術註解：驗證 SoftDeletes 預設查詢隔離，避免已刪除資料被一般列表誤取造成資料外洩。
    expect(Vehicle::query()->whereKey($vehicle->id)->exists())->toBeFalse();
    expect(Vehicle::withTrashed()->whereKey($vehicle->id)->exists())->toBeTrue();
});

it('VIN normalize 正常', function (): void {
    $vehicle = makeVehicle(1, 10, 'STK-010', '  ab c123  ');

    // 技術註解：VIN 標準化可降低重複比對失敗與髒資料風險。
    expect($vehicle->fresh()->vin)->toBe('ABC123');
});
