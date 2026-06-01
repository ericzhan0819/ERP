<?php

use App\Models\ActivityLog;
use App\Models\Module;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 技術註解：成本路由掛在 vehicles 模組下，需先建立 module registry 避免 module.access 前置阻擋干擾案例焦點。
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
    Permission::findOrCreate('module.vehicles.update', 'web');
    Permission::findOrCreate('module.vehicles.costs.view', 'web');
    Permission::findOrCreate('module.vehicles.costs.create', 'web');
    Permission::findOrCreate('module.vehicles.costs.update', 'web');
});

function ensureVehicleCostTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'Company '.$companyId,
            'code' => 'VC'.$companyId,
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
                'code' => 'VB'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function makeVehicleCostUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    ensureVehicleCostTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'Vehicle Cost User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function makeVehicleCostVehicle(int $companyId, int $branchId, string $stock, string $vin): Vehicle
{
    ensureVehicleCostTenantRows($companyId, $branchId);

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

/** @return array<string, mixed> */
function validVehicleCostPayload(array $overrides = []): array
{
    return array_merge([
        'cost_type' => 'repair',
        'description' => 'Test Cost',
        'amount' => 15000,
        'cost_date' => '2026-06-01',
        'vendor_name' => 'Vendor A',
        'payment_status' => 'unpaid',
        'paid_at' => null,
        'internal_notes' => 'internal-only-note',
    ], $overrides);
}

function makeVehicleCostRecord(Vehicle $vehicle, User $actor, array $overrides = []): VehicleCost
{
    return VehicleCost::create(array_merge([
        'company_id' => $vehicle->company_id,
        'branch_id' => $vehicle->branch_id,
        'vehicle_id' => $vehicle->id,
        'cost_type' => 'repair',
        'description' => 'Existing Cost',
        'amount' => 12000,
        'cost_date' => '2026-05-31',
        'vendor_name' => 'Vendor B',
        'payment_status' => 'unpaid',
        'paid_at' => null,
        'internal_notes' => 'old-internal-note',
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ], $overrides));
}

it('有 module.vehicles.costs.create 可新增成本', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-create-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.costs.create');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-CRT-001', 'vin-cost-crt-001');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.costs.store', $vehicle->id), validVehicleCostPayload())
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $this->assertDatabaseHas('vehicle_costs', [
        'vehicle_id' => $vehicle->id,
        'company_id' => 1,
        'branch_id' => 10,
        'cost_type' => 'repair',
    ]);
});

it('無 module.vehicles.costs.create 新增 403', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-create-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-CRT-002', 'vin-cost-crt-002');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.costs.store', $vehicle->id), validVehicleCostPayload())
        ->assertForbidden();
});

it('跨 company vehicle 不可新增且 404 優先', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-cross-company-create@example.com', 1, 10);
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.costs.create');
    $crossCompanyVehicle = makeVehicleCostVehicle(2, 20, 'STK-COST-XCOMP-001', 'vin-cost-xcomp-001');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.costs.store', $crossCompanyVehicle->id), validVehicleCostPayload())
        ->assertNotFound();
});

it('create 時前端竄改 company_id/branch_id/vehicle_id/created_by/updated_by 不生效', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-create-payload-locked@example.com', 1, 10);
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.costs.create');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-PAYLOAD-001', 'vin-cost-payload-001');
    $another = makeVehicleCostVehicle(1, 10, 'STK-COST-PAYLOAD-002', 'vin-cost-payload-002');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.costs.store', $vehicle->id), validVehicleCostPayload([
            'company_id' => 999,
            'branch_id' => 888,
            'vehicle_id' => $another->id,
            'created_by' => 777,
            'updated_by' => 666,
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $cost = VehicleCost::query()->latest('id')->firstOrFail();
    expect($cost->company_id)->toBe(1)
        ->and($cost->branch_id)->toBe(10)
        ->and($cost->vehicle_id)->toBe($vehicle->id)
        ->and($cost->created_by)->toBe($user->id)
        ->and($cost->updated_by)->toBe($user->id);
});

it('有 module.vehicles.costs.view 時 Vehicle Show payload 可見 vehicleCosts 與 vehicleCostSummary', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-show-view-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.costs.view');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-SHOW-001', 'vin-cost-show-001');
    makeVehicleCostRecord($vehicle, $user, ['amount' => 31000, 'payment_status' => 'paid']);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('vehicleCosts.0.cost_type', 'repair')
            ->where('vehicleCostSummary.count', 1)
            ->where('vehicleCostSummary.paid_amount', '31000')
        );
});

it('無 module.vehicles.costs.view 時 Vehicle Show payload 不可見 vehicleCosts、vehicleCostSummary 且不可見成本敏感欄位', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-show-view-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-SHOW-002', 'vin-cost-show-002');
    makeVehicleCostRecord($vehicle, $user, ['internal_notes' => 'super-secret']);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            // 技術註解：目前後端固定輸出 key 並以 null 表示未授權，不應包含任何成本資料內容。
            ->where('vehicleCosts', null)
            ->where('vehicleCostSummary', null)
            ->where('can.view_vehicle_costs', false)
            ->missing('vehicleCosts.0.internal_notes')
            ->missing('vehicleCosts.0.company_id')
            ->missing('vehicleCosts.0.branch_id')
            ->missing('vehicleCosts.0.vehicle_id')
        );
});

it('Vehicle Show 不再回傳成本建立所需選項 props（避免詳情頁承載 mutation UI）', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-show-no-create-props@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.costs.view');
    $user->givePermissionTo('module.vehicles.costs.create');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-SHOW-003', 'vin-cost-show-003');
    makeVehicleCostRecord($vehicle, $user);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.create_vehicle_costs', true)
            // 技術註解：Show 僅保留只讀成本資訊，不再提供建立表單所需字典資料，防止 UI 誤導為可直接變更。
            ->where('vehicleCostTypes', null)
            ->where('vehicleCostPaymentStatuses', null)
        );
});

it('有 module.vehicles.costs.view 時 Vehicle Edit payload 可見 vehicleCosts 與 vehicleCostSummary', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-edit-view-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $user->givePermissionTo('module.vehicles.costs.view');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-EDIT-001', 'vin-cost-edit-001');
    makeVehicleCostRecord($vehicle, $user, ['amount' => 42000, 'payment_status' => 'paid']);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.edit', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('vehicleCosts.0.cost_type', 'repair')
            ->where('vehicleCostSummary.count', 1)
            ->where('vehicleCostSummary.paid_amount', '42000')
            ->where('can.view_vehicle_costs', true)
        );
});

it('無 module.vehicles.costs.view 時 Vehicle Edit payload 不可見成本敏感資料', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-edit-view-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-EDIT-002', 'vin-cost-edit-002');
    makeVehicleCostRecord($vehicle, $user, ['internal_notes' => 'secret-edit']);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.edit', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('vehicleCosts', null)
            ->where('vehicleCostSummary', null)
            ->where('vehicleCostTypes', null)
            ->where('vehicleCostPaymentStatuses', null)
            ->where('can.view_vehicle_costs', false)
            ->missing('vehicleCosts.0.internal_notes')
            ->missing('vehicleCosts.0.company_id')
            ->missing('vehicleCosts.0.branch_id')
            ->missing('vehicleCosts.0.vehicle_id')
        );
});

it('有 module.vehicles.costs.create 時 Vehicle Edit payload 提供新增成本能力', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-edit-create-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $user->givePermissionTo('module.vehicles.costs.view');
    $user->givePermissionTo('module.vehicles.costs.create');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-EDIT-003', 'vin-cost-edit-003');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.edit', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.create_vehicle_costs', true)
            ->where('vehicleCostTypes.repair', '維修')
            ->where('vehicleCostPaymentStatuses.unpaid', '未付款')
        );
});

it('無 module.vehicles.costs.create 時 Vehicle Edit payload 不提供新增成本能力', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-edit-create-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $user->givePermissionTo('module.vehicles.costs.view');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-EDIT-004', 'vin-cost-edit-004');

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.edit', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.create_vehicle_costs', false)
            ->where('can.view_vehicle_costs', true)
        );
});

it('有 module.vehicles.costs.update 可更新成本', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-update-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.costs.update');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-UPD-001', 'vin-cost-upd-001');
    $cost = makeVehicleCostRecord($vehicle, $user);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.costs.update', [$vehicle->id, $cost->id]), validVehicleCostPayload([
            'description' => 'Updated Cost Desc',
            'amount' => 28000,
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $cost->refresh();
    expect($cost->description)->toBe('Updated Cost Desc')
        ->and((float) $cost->amount)->toBe(28000.0);
});

it('無 module.vehicles.costs.update 更新 403', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-update-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-UPD-002', 'vin-cost-upd-002');
    $cost = makeVehicleCostRecord($vehicle, $user);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.costs.update', [$vehicle->id, $cost->id]), validVehicleCostPayload())
        ->assertForbidden();
});

it('跨 company cost 不可更新且 404 優先', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-cross-company-update@example.com', 1, 10);
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.costs.update');
    $crossCompanyUser = makeVehicleCostUser('vehicle-cost-cross-company-owner@example.com', 2, 20);
    $crossCompanyVehicle = makeVehicleCostVehicle(2, 20, 'STK-COST-XCOMP-UPD-001', 'vin-cost-xcomp-upd-001');
    $crossCompanyCost = makeVehicleCostRecord($crossCompanyVehicle, $crossCompanyUser);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.costs.update', [$crossCompanyVehicle->id, $crossCompanyCost->id]), validVehicleCostPayload())
        ->assertNotFound();
});

it('update 會更新 updated_by', function (): void {
    $creator = makeVehicleCostUser('vehicle-cost-updated-by-creator@example.com');
    $updater = makeVehicleCostUser('vehicle-cost-updated-by-updater@example.com');
    $updater->givePermissionTo('module.vehicles.view');
    $updater->givePermissionTo('module.vehicles.costs.update');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-UPD-AUDIT-001', 'vin-cost-upd-audit-001');
    $cost = makeVehicleCostRecord($vehicle, $creator, ['updated_by' => $creator->id]);

    $this->actingAs($updater)
        ->patch(route('employee-system.vehicles.costs.update', [$vehicle->id, $cost->id]), validVehicleCostPayload())
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $cost->refresh();
    expect($cost->updated_by)->toBe($updater->id);
});

it('create 會產生 activity log event vehicle_cost.created 且 audit new_values 不包含 internal_notes', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-log-create@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.costs.create');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-LOG-CRT-001', 'vin-cost-log-crt-001');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.costs.store', $vehicle->id), validVehicleCostPayload([
            'internal_notes' => 'secret-note-create',
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $log = ActivityLog::query()->where('event', 'vehicle_cost.created')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and(($log?->new_values ?? []))->toBeArray()
        ->and(array_key_exists('internal_notes', $log?->new_values ?? []))->toBeFalse();
});

it('update 會產生 activity log event vehicle_cost.updated 且 audit old/new values 不包含 internal_notes', function (): void {
    $user = makeVehicleCostUser('vehicle-cost-log-update@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.costs.update');
    $vehicle = makeVehicleCostVehicle(1, 10, 'STK-COST-LOG-UPD-001', 'vin-cost-log-upd-001');
    $cost = makeVehicleCostRecord($vehicle, $user, [
        'description' => 'Before Update',
        'internal_notes' => 'secret-note-before',
    ]);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.costs.update', [$vehicle->id, $cost->id]), validVehicleCostPayload([
            'description' => 'After Update',
            'internal_notes' => 'secret-note-after',
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $log = ActivityLog::query()->where('event', 'vehicle_cost.updated')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log?->old_values['description'] ?? null)->toBe('Before Update')
        ->and($log?->new_values['description'] ?? null)->toBe('After Update')
        ->and(array_key_exists('internal_notes', $log?->old_values ?? []))->toBeFalse()
        ->and(array_key_exists('internal_notes', $log?->new_values ?? []))->toBeFalse();
});
