<?php

use App\Models\Module;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleStockNumberService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
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

    $vehicle = Vehicle::query()->where('company_id', 1)->where('branch_id', 10)->latest('id')->first();
    expect($vehicle)->not->toBeNull()
        ->and($vehicle?->stock_number)->toMatch('/^VH-\d{6}-\d{4}$/');
});

it('store 時 request 傳入 company_id 仍強制使用登入者 company_id', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-company-locked@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload([
            'vin' => 'vin-new-002',
            'company_id' => 2,
        ]))
        ->assertRedirect();

    $vehicle = Vehicle::query()->where('vin', 'VIN-NEW-002')->firstOrFail();
    expect($vehicle->company_id)->toBe(1);
});

it('store 時 request 傳入 branch_id 仍強制使用登入者 branch_id', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-branch-locked@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload([
            'vin' => 'vin-new-003',
            'branch_id' => 99,
        ]))
        ->assertRedirect();

    $vehicle = Vehicle::query()->where('vin', 'VIN-NEW-003')->firstOrFail();
    expect($vehicle->branch_id)->toBe(10);
});

it('store 時 created_by 與 updated_by 必須是登入者 id', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-auditor@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload([
            'vin' => 'vin-new-004',
        ]))
        ->assertRedirect();

    $vehicle = Vehicle::query()->where('vin', 'VIN-NEW-004')->firstOrFail();
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
            'vin' => 'vin-xbranch-001',
        ]))
        ->assertNotFound();
});

it('store 接受合法 lifecycle_status', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-lifecycle-valid@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload([
            'vin' => 'vin-lc-valid-001',
            'lifecycle_status' => 'reserved',
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('vehicles', [
        'vin' => 'VIN-LC-VALID-001',
        'lifecycle_status' => 'reserved',
    ]);
});

it('store 拒絕非法 lifecycle_status', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-lifecycle-invalid@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->from(route('employee-system.vehicles.create'))
        ->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload([
            'vin' => 'vin-lc-invalid-001',
            'lifecycle_status' => 'invalid_status',
        ]))
        ->assertRedirect(route('employee-system.vehicles.create'))
        ->assertSessionHasErrors(['lifecycle_status']);
});

it('store 缺少 lifecycle_status 應 validation error', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-lifecycle-missing@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $payload = validVehiclePayload([
        'vin' => 'vin-lc-missing-001',
    ]);
    unset($payload['lifecycle_status']);

    $this->from(route('employee-system.vehicles.create'))
        ->actingAs($user)
        ->post(route('employee-system.vehicles.store'), $payload)
        ->assertRedirect(route('employee-system.vehicles.create'))
        ->assertSessionHasErrors(['lifecycle_status']);
});

it('update 接受合法 lifecycle_status', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-update-lifecycle-valid@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleCrudRecord(1, 10, 'STK-LC-UPD-VALID-001', 'vin-lc-upd-valid-001');

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.update', $vehicle->id), validVehiclePayload([
            'vin' => 'vin-lc-upd-valid-001',
            'lifecycle_status' => 'sold',
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $vehicle->refresh();
    expect($vehicle->lifecycle_status)->toBe('sold');
});

it('update 拒絕非法 lifecycle_status', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-update-lifecycle-invalid@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleCrudRecord(1, 10, 'STK-LC-UPD-INVALID-001', 'vin-lc-upd-invalid-001');

    $this->from(route('employee-system.vehicles.edit', $vehicle->id))
        ->actingAs($user)
        ->patch(route('employee-system.vehicles.update', $vehicle->id), validVehiclePayload([
            'vin' => 'vin-lc-upd-invalid-001',
            'lifecycle_status' => 'invalid_status',
        ]))
        ->assertRedirect(route('employee-system.vehicles.edit', $vehicle->id))
        ->assertSessionHasErrors(['lifecycle_status']);
});

it('store 自動產生 stock_number 且符合格式', function (): void {
    Carbon::setTestNow('2026-05-20 10:00:00');

    $user = makeVehicleCrudUser('vehicle-crud-auto-stock-format@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload(['vin' => 'vin-auto-format-001']))
        ->assertRedirect();

    $vehicle = Vehicle::query()->where('vin', 'VIN-AUTO-FORMAT-001')->firstOrFail();
    expect($vehicle->stock_number)->toMatch('/^VH-\d{6}-\d{4}$/');

    Carbon::setTestNow();
});

it('同 company 同 period 連續新增兩台會遞增流水號', function (): void {
    Carbon::setTestNow('2026-05-20 10:00:00');

    $user = makeVehicleCrudUser('vehicle-crud-auto-stock-sequence@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload(['vin' => 'vin-auto-seq-001']))
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload(['vin' => 'vin-auto-seq-002']))
        ->assertRedirect();

    $first = Vehicle::query()->where('vin', 'VIN-AUTO-SEQ-001')->firstOrFail();
    $second = Vehicle::query()->where('vin', 'VIN-AUTO-SEQ-002')->firstOrFail();

    expect($first->stock_number)->toBe('VH-202605-0001')
        ->and($second->stock_number)->toBe('VH-202605-0002');

    Carbon::setTestNow();
});

it('不同 company 同 period 各自從 0001 開始', function (): void {
    Carbon::setTestNow('2026-05-20 10:00:00');

    $userCompany1 = makeVehicleCrudUser('vehicle-crud-auto-stock-company1@example.com', 1, 10);
    $userCompany1->givePermissionTo('module.vehicles.view');
    $userCompany1->givePermissionTo('module.vehicles.create');

    $userCompany2 = makeVehicleCrudUser('vehicle-crud-auto-stock-company2@example.com', 2, 20);
    $userCompany2->givePermissionTo('module.vehicles.view');
    $userCompany2->givePermissionTo('module.vehicles.create');

    $this->actingAs($userCompany1)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload(['vin' => 'vin-auto-company-001']))
        ->assertRedirect();

    $this->actingAs($userCompany2)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload(['vin' => 'vin-auto-company-002']))
        ->assertRedirect();

    $vehicleCompany1 = Vehicle::query()->where('vin', 'VIN-AUTO-COMPANY-001')->firstOrFail();
    $vehicleCompany2 = Vehicle::query()->where('vin', 'VIN-AUTO-COMPANY-002')->firstOrFail();

    expect($vehicleCompany1->stock_number)->toBe('VH-202605-0001')
        ->and($vehicleCompany2->stock_number)->toBe('VH-202605-0001');

    Carbon::setTestNow();
});

it('store 傳入 hacked stock_number 會被忽略', function (): void {
    Carbon::setTestNow('2026-05-20 10:00:00');

    $user = makeVehicleCrudUser('vehicle-crud-store-hacked-stock@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.store'), validVehiclePayload([
            'vin' => 'vin-hacked-store-001',
            'stock_number' => 'HACKED-001',
        ]))
        ->assertRedirect();

    $vehicle = Vehicle::query()->where('vin', 'VIN-HACKED-STORE-001')->firstOrFail();
    expect($vehicle->stock_number)->not->toBe('HACKED-001')
        ->and($vehicle->stock_number)->toBe('VH-202605-0001');

    Carbon::setTestNow();
});

it('使用者缺 company_id 時 store 回 422 且不建立 vehicle', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-missing-company@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');
    $user->update(['company_id' => null]);

    $response = $this->actingAs($user)
        ->postJson(route('employee-system.vehicles.store'), validVehiclePayload([
            'vin' => 'vin-missing-company-001',
        ]));

    $response
        ->assertStatus(422)
        ->assertJson([
            'message' => '使用者尚未設定公司或分店，無法建立車輛。',
        ]);

    // 技術註解：tenant 邊界異常時必須在控制器提早中斷，避免任何車輛資料被建立。
    $this->assertDatabaseMissing('vehicles', [
        'vin' => 'VIN-MISSING-COMPANY-001',
    ]);
});

it('使用者缺 branch_id 時 store 回 422 且不建立 vehicle', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-store-missing-branch@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.create');
    $user->update(['branch_id' => null]);

    $response = $this->actingAs($user)
        ->postJson(route('employee-system.vehicles.store'), validVehiclePayload([
            'vin' => 'vin-missing-branch-001',
        ]));

    $response
        ->assertStatus(422)
        ->assertJson([
            'message' => '使用者尚未設定公司或分店，無法建立車輛。',
        ]);

    // 技術註解：分店未綁定屬於 tenant 邊界不完整，必須阻止寫入以避免資料落在不明範圍。
    $this->assertDatabaseMissing('vehicles', [
        'vin' => 'VIN-MISSING-BRANCH-001',
    ]);
});

it('VehicleStockNumberService generate 傳入 0 會丟 InvalidArgumentException', function (): void {
    $service = app(VehicleStockNumberService::class);

    expect(fn () => $service->generate(0))->toThrow(InvalidArgumentException::class);
});

it('DatabaseSeeder 後 admin@example.com 會綁定 company_id 與 branch_id', function (): void {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($admin->company_id)->not->toBeNull()
        ->and($admin->company_id)->toBeGreaterThan(0)
        ->and($admin->branch_id)->not->toBeNull()
        ->and($admin->branch_id)->toBeGreaterThan(0);
});

it('DatabaseSeeder 後 staff@example.com 若存在會綁定 company_id 與 branch_id', function (): void {
    $this->seed(DatabaseSeeder::class);

    $staff = User::query()->where('email', 'staff@example.com')->first();

    if ($staff !== null) {
        expect($staff->company_id)->not->toBeNull()
            ->and($staff->company_id)->toBeGreaterThan(0)
            ->and($staff->branch_id)->not->toBeNull()
            ->and($staff->branch_id)->toBeGreaterThan(0);
    }
});

it('update 傳入 hacked stock_number 無法修改', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-update-hacked-stock@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleCrudRecord(1, 10, 'STK-IMMUTABLE-001', 'vin-immutable-001');

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.update', $vehicle->id), validVehiclePayload([
            'vin' => 'vin-immutable-updated-001',
            'stock_number' => 'HACKED-002',
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $vehicle->refresh();
    expect($vehicle->stock_number)->toBe('STK-IMMUTABLE-001');
});

it('update 不傳 stock_number 也能成功', function (): void {
    $user = makeVehicleCrudUser('vehicle-crud-update-without-stock@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleCrudRecord(1, 10, 'STK-NO-STOCK-UPD-001', 'vin-no-stock-upd-001');

    $payload = validVehiclePayload([
        'vin' => 'vin-no-stock-upd-002',
        'model' => 'Yaris',
    ]);
    unset($payload['stock_number']);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.update', $vehicle->id), $payload)
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $vehicle->refresh();
    expect($vehicle->stock_number)->toBe('STK-NO-STOCK-UPD-001')
        ->and($vehicle->model)->toBe('Yaris');
});
