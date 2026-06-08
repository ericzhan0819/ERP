<?php

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Module;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 技術註解：銷售路由掛在 vehicles 模組下，測試需建立 module registry 避免 module.access 前置門禁干擾案例焦點。
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
    Permission::findOrCreate('module.vehicles.sales.view', 'web');
    Permission::findOrCreate('module.vehicles.sales.create', 'web');
    Permission::findOrCreate('module.vehicles.sales.update', 'web');
    Permission::findOrCreate('module.vehicles.sales.completion.view', 'web');
    Permission::findOrCreate('module.vehicles.sales.completion.confirm', 'web');
    Permission::findOrCreate('module.receivables.mark-sold', 'web');
    Permission::findOrCreate('module.vehicles.costs.view', 'web');
    Permission::findOrCreate('module.vehicles.pricing.view', 'web');
});

function ensureVehicleSaleTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'Sale Company '.$companyId,
            'code' => 'VS'.$companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            [
                'company_id' => $companyId,
                'name' => 'Sale Branch '.$branchId,
                'code' => 'VSB'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function makeVehicleSaleUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    ensureVehicleSaleTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'Vehicle Sale User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function makeVehicleSaleVehicle(int $companyId, int $branchId, string $stock, string $vin): Vehicle
{
    ensureVehicleSaleTenantRows($companyId, $branchId);

    return Vehicle::create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'stock_number' => $stock,
        'vin' => $vin,
        'brand' => 'Toyota',
        'model' => 'Camry',
        'model_year' => 2023,
        'lifecycle_status' => 'in_stock',
    ]);
}

function makeVehicleSaleCustomer(int $companyId, int $branchId, string $number, array $overrides = []): Customer
{
    ensureVehicleSaleTenantRows($companyId, $branchId);

    return Customer::create(array_merge([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'customer_number' => $number,
        'name' => '主檔客戶',
        'phone' => '0987654321',
        'id_number' => 'A123456789',
        'birthday' => '1990-01-01',
        'address' => '敏感地址',
        'email' => 'customer-secret@example.com',
        'line_id' => 'secret-line',
        'source' => 'walk-in',
        'notes' => 'customer sensitive notes',
        'status' => 'active',
    ], $overrides));
}

/** @return array<string, mixed> */
function validVehicleSalePayload(array $overrides = []): array
{
    return array_merge([
        'customer_name' => '王小明',
        'customer_phone' => '0912345678',
        'customer_id' => null,
        'sale_price' => 880000,
        'deposit_amount' => 50000,
        'paid_amount' => 100000,
        'sale_status' => 'reserved',
        'sold_at' => '2026-06-02',
        'salesperson_name' => '業務甲',
        'commission_amount' => 12000,
        'notes' => 'MVP sale note',
    ], $overrides);
}

function makeVehicleSaleRecord(Vehicle $vehicle, User $actor, array $overrides = []): VehicleSale
{
    return VehicleSale::create(array_merge([
        'company_id' => $vehicle->company_id,
        'branch_id' => $vehicle->branch_id,
        'vehicle_id' => $vehicle->id,
        'customer_id' => null,
        'customer_name' => 'Existing Customer',
        'customer_phone' => '0900000000',
        'sale_price' => 760000,
        'deposit_amount' => 30000,
        'paid_amount' => 60000,
        'sale_status' => 'draft',
        'sold_at' => null,
        'salesperson_name' => 'Existing Sales',
        'commission_amount' => 8000,
        'notes' => 'existing note',
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ], $overrides));
}

function makeVehicleSalePaymentRecord(VehicleSale $sale, User $actor, float $amount, string $status = 'received'): VehicleSalePayment
{
    return VehicleSalePayment::create([
        'company_id' => $sale->company_id,
        'branch_id' => $sale->branch_id,
        'vehicle_id' => $sale->vehicle_id,
        'vehicle_sale_id' => $sale->id,
        'customer_id' => $sale->customer_id,
        'payment_number' => 'PAY-SALE-'.uniqid(),
        'payment_type' => 'deposit',
        'payment_method' => 'cash',
        'amount' => $amount,
        'paid_at' => now()->toDateString(),
        'status' => $status,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);
}

it('VehicleSale model 可儲存與讀取交易完成資料欄位', function (): void {
    expect(Schema::hasColumns('vehicle_sales', ['completed_at', 'completed_by', 'completion_note']))->toBeTrue();

    $user = makeVehicleSaleUser('vehicle-sale-completion-model@example.com');
    $completer = makeVehicleSaleUser('vehicle-sale-completer@example.com');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-COMPLETE-001', 'vin-sale-complete-001');
    $completedAt = now()->setMicrosecond(0);

    $sale = makeVehicleSaleRecord($vehicle, $user, [
        'completed_at' => $completedAt,
        'completed_by' => $completer->id,
        'completion_note' => '交易資料已確認，保留給後續 completion action 使用。',
    ])->fresh();

    expect($sale->completed_at?->toDateTimeString())->toBe($completedAt->toDateTimeString())
        ->and($sale->completed_by)->toBe($completer->id)
        ->and($sale->completion_note)->toBe('交易資料已確認，保留給後續 completion action 使用。')
        ->and($sale->completer)->not->toBeNull()
        ->and($sale->completer?->id)->toBe($completer->id);
});

it('有 sales.view 權限者 Show/Edit payload 看得到 sales', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-view-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $user->givePermissionTo('module.vehicles.sales.view');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-VIEW-001', 'vin-sale-view-001');
    makeVehicleSaleRecord($vehicle, $user, ['sale_status' => 'reserved']);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('vehicleSales.0.sale_status', 'reserved')
            ->where('vehicleSales.0.sale_status_label', '保留')
            ->where('vehicleSaleSummary.count', 1)
            ->where('can.view_vehicle_sales', true)
        );

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.edit', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('vehicleSales.0.customer_phone', '0900000000')
            ->where('vehicleSaleStatuses.sold', '成交')
        );
});

it('有 sales.view 權限者 Show/Edit vehicleSales payload 看得到唯讀 completion summary', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-completion-summary@example.com');
    $completer = makeVehicleSaleUser('vehicle-sale-completion-summary-completer@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.update', 'module.vehicles.sales.view']);
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-COMP-SUM-001', 'vin-sale-comp-sum-001');
    $completedAt = now()->setMicrosecond(0);
    makeVehicleSaleRecord($vehicle, $user, [
        'sale_status' => 'sold',
        'completed_at' => $completedAt,
        'completed_by' => $completer->id,
        'completion_note' => '車輛頁唯讀完成備註',
    ]);

    foreach (['show', 'edit'] as $action) {
        $this->actingAs($user)
            ->get(route('employee-system.vehicles.'.$action, $vehicle->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicleSales.0.completion.status', 'completed')
                ->where('vehicleSales.0.completion.status_label', '已完成交易')
                ->where('vehicleSales.0.completion.completed_at', $completedAt->format('Y-m-d H:i:s'))
                ->where('vehicleSales.0.completion.completed_by_name', $completer->name)
                ->where('vehicleSales.0.completion.note', '車輛頁唯讀完成備註')
                ->missing('vehicleSales.0.completion.can_complete')
                ->missing('vehicleSales.0.completion.block_reason')
                ->missing('vehicleSales.0.completion.complete_route')
            );
    }
});

it('有 sales.view 時 sales payload 僅回傳 Customer 基本資訊且排除敏感個資', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-customer-payload@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $user->givePermissionTo('module.vehicles.sales.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-CUS-PAYLOAD-001', 'vin-sale-cus-payload-001');
    $customer = makeVehicleSaleCustomer(1, 10, 'CU-202606-0003');
    makeVehicleSaleRecord($vehicle, $user, [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
    ]);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('vehicleSales.0.customer.id', $customer->id)
            ->where('vehicleSales.0.customer.customer_number', 'CU-202606-0003')
            ->where('vehicleSales.0.customer.name', '主檔客戶')
            ->where('vehicleSales.0.customer.phone', '0987654321')
            ->missing('vehicleSales.0.customer.id_number')
            ->missing('vehicleSales.0.customer.birthday')
            ->missing('vehicleSales.0.customer.address')
            ->missing('vehicleSales.0.customer.email')
            ->missing('vehicleSales.0.customer.line_id')
            ->missing('vehicleSales.0.customer.notes')
            ->missing('vehicleSales.0.customer.source')
        );

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.edit', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('customerOptions.0.customer_number', 'CU-202606-0003')
            ->where('customerOptions.0.status', 'active')
            ->missing('customerOptions.0.id_number')
            ->missing('customerOptions.0.birthday')
            ->missing('customerOptions.0.address')
            ->missing('customerOptions.0.email')
            ->missing('customerOptions.0.line_id')
            ->missing('customerOptions.0.notes')
            ->missing('customerOptions.0.source')
        );
});

it('create 可帶 customer_id 並以同 tenant Customer 主檔覆寫 snapshot', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-customer-create@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-CUS-CRT-001', 'vin-sale-cus-crt-001');
    $customer = makeVehicleSaleCustomer(1, 10, 'CU-202606-0001', [
        'name' => '主檔王小明',
        'phone' => '0999888777',
    ]);

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload([
            'customer_id' => $customer->id,
            'customer_name' => '前端竄改姓名',
            'customer_phone' => '0000000000',
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $sale = VehicleSale::query()->latest('id')->firstOrFail();
    expect($sale->customer_id)->toBe($customer->id)
        ->and($sale->customer_name)->toBe('主檔王小明')
        ->and($sale->customer_phone)->toBe('0999888777');
});

it('跨 company 或 branch 的 customer_id 建立銷售回 404 防止 IDOR', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-customer-create-idor@example.com', 1, 10);
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-CUS-IDOR-001', 'vin-sale-cus-idor-001');
    $crossCustomer = makeVehicleSaleCustomer(2, 20, 'CU-202606-0999');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload([
            'customer_id' => $crossCustomer->id,
        ]))
        ->assertNotFound();
});

it('update 可改 customer_id 並重新套用 Customer snapshot', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-customer-update@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.update');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-CUS-UPD-001', 'vin-sale-cus-upd-001');
    $sale = makeVehicleSaleRecord($vehicle, $user, ['sale_status' => 'cancelled']);
    $customer = makeVehicleSaleCustomer(1, 10, 'CU-202606-0002', [
        'name' => '更新主檔客戶',
        'phone' => '0911111222',
    ]);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.update', [$vehicle->id, $sale->id]), validVehicleSalePayload([
            'customer_id' => $customer->id,
            'customer_name' => '更新竄改姓名',
            'customer_phone' => '123',
            'sale_status' => 'cancelled',
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $sale->refresh();
    expect($sale->customer_id)->toBe($customer->id)
        ->and($sale->customer_name)->toBe('更新主檔客戶')
        ->and($sale->customer_phone)->toBe('0911111222');
});

it('跨 tenant customer_id 更新銷售回 404', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-customer-update-idor@example.com', 1, 10);
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.update');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-CUS-UPD-IDOR-001', 'vin-sale-cus-upd-idor-001');
    $sale = makeVehicleSaleRecord($vehicle, $user, ['sale_status' => 'cancelled']);
    $crossCustomer = makeVehicleSaleCustomer(1, 11, 'CU-202606-0888');

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.update', [$vehicle->id, $sale->id]), validVehicleSalePayload([
            'customer_id' => $crossCustomer->id,
            'sale_status' => 'cancelled',
        ]))
        ->assertNotFound();
});

it('無 sales.view 權限者 payload 不回傳 sales 且不暴露銷售敏感欄位', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-view-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-VIEW-002', 'vin-sale-view-002');
    makeVehicleSaleRecord($vehicle, $user, ['customer_phone' => 'secret-phone', 'commission_amount' => 99999]);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('vehicleSales')
            ->missing('vehicleSaleSummary')
            ->missing('vehicleSales.0.customer_phone')
                ->missing('vehicleSales.0.commission_amount')
                ->missing('vehicleSales.0.completion')
                ->where('can.view_vehicle_sales', false)
        );

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.edit', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('vehicleSales')
            ->missing('vehicleSaleStatuses')
        );
});

it('有 sales.create 權限可建立銷售且後端決定系統欄位', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-create-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-CRT-001', 'vin-sale-crt-001');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload())
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $sale = VehicleSale::query()->latest('id')->firstOrFail();
    expect($sale->company_id)->toBe(1)
        ->and($sale->branch_id)->toBe(10)
        ->and($sale->vehicle_id)->toBe($vehicle->id)
        ->and($sale->created_by)->toBe($user->id)
        ->and($sale->updated_by)->toBe($user->id);
});

it('已成交車輛不可建立新的銷售紀錄', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-sold-create-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-SOLD-DENY-001', 'vin-sale-sold-deny-001');
    $vehicle->update(['lifecycle_status' => 'sold']);

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload(['sale_status' => 'reserved']))
        ->assertStatus(422)
        ->assertJsonPath('message', '已成交車輛不可建立新的銷售紀錄。');
});

it('無 sales.create 權限建立回 403', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-create-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-CRT-002', 'vin-sale-crt-002');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload())
        ->assertForbidden();
});

it('有 sales.update 權限可更新銷售', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-update-allow@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.update');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-UPD-001', 'vin-sale-upd-001');
    $sale = makeVehicleSaleRecord($vehicle, $user);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.update', [$vehicle->id, $sale->id]), validVehicleSalePayload([
            'customer_name' => '更新客戶',
            'sale_status' => 'sold',
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $sale->refresh();
    expect($sale->customer_name)->toBe('更新客戶')
        ->and($sale->sale_status)->toBe('sold')
        ->and($sale->updated_by)->toBe($user->id);
});

it('無 sales.update 權限更新回 403', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-update-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-UPD-002', 'vin-sale-upd-002');
    $sale = makeVehicleSaleRecord($vehicle, $user);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.update', [$vehicle->id, $sale->id]), validVehicleSalePayload())
        ->assertForbidden();
});

it('前端嘗試覆寫系統欄位或成本毛利欄位時拒絕', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-payload-locked@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-PAYLOAD-001', 'vin-sale-payload-001');
    $another = makeVehicleSaleVehicle(1, 10, 'STK-SALE-PAYLOAD-002', 'vin-sale-payload-002');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload([
            'company_id' => 999,
            'branch_id' => 888,
            'vehicle_id' => $another->id,
            'created_by' => 777,
            'updated_by' => 666,
            'gross_profit' => 12345,
        ]))
        ->assertForbidden();
});

it('Vehicle Sales request 夾帶 Customer 敏感個資欄位時拒絕', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-sensitive-fields-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-SENSITIVE-DENY-001', 'vin-sale-sensitive-deny-001');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload([
            'id_number' => 'A123456789',
            'birthday' => '1990-01-01',
            'address' => '不應進入銷售請求',
        ]))
        ->assertForbidden();
});

it('跨 company/branch 的 vehicle 或 sale 回 404', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-cross-tenant@example.com', 1, 10);
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $user->givePermissionTo('module.vehicles.sales.update');
    $crossUser = makeVehicleSaleUser('vehicle-sale-cross-owner@example.com', 2, 20);
    $crossVehicle = makeVehicleSaleVehicle(2, 20, 'STK-SALE-XTEN-001', 'vin-sale-xten-001');
    $crossSale = makeVehicleSaleRecord($crossVehicle, $crossUser);

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $crossVehicle->id), validVehicleSalePayload())
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.update', [$crossVehicle->id, $crossSale->id]), validVehicleSalePayload())
        ->assertNotFound();
});

it('reserved/sold/cancelled 同步 vehicle.lifecycle_status 且取消已售不回庫存', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-lifecycle@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $user->givePermissionTo('module.vehicles.sales.update');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-LIFE-001', 'vin-sale-life-001');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload(['sale_status' => 'reserved']))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));
    expect($vehicle->fresh()->lifecycle_status)->toBe('reserved');

    $sale = VehicleSale::query()->latest('id')->firstOrFail();
    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.update', [$vehicle->id, $sale->id]), validVehicleSalePayload(['sale_status' => 'sold']))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));
    expect($vehicle->fresh()->lifecycle_status)->toBe('sold');

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.update', [$vehicle->id, $sale->id]), validVehicleSalePayload(['sale_status' => 'cancelled']))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));
    expect($vehicle->fresh()->lifecycle_status)->toBe('sold');

    $secondVehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-LIFE-002', 'vin-sale-life-002');
    $cancelSale = makeVehicleSaleRecord($secondVehicle, $user, ['sale_status' => 'reserved']);
    $secondVehicle->update(['lifecycle_status' => 'reserved']);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.update', [$secondVehicle->id, $cancelSale->id]), validVehicleSalePayload(['sale_status' => 'cancelled']))
        ->assertRedirect(route('employee-system.vehicles.show', $secondVehicle->id));
    expect($secondVehicle->fresh()->lifecycle_status)->toBe('in_stock');
});

it('draft 不會強制改寫 vehicle.lifecycle_status', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-draft-lifecycle@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-DRAFT-LIFE-001', 'vin-sale-draft-life-001');
    $vehicle->update(['lifecycle_status' => 'reserved']);

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload([
            'sale_status' => 'draft',
            'sale_price' => null,
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    expect($vehicle->fresh()->lifecycle_status)->toBe('reserved');
});

it('已成交銷售紀錄不可改回保留狀態', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-sold-to-reserved-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.update');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-SOLD-RESERVED-001', 'vin-sale-sold-reserved-001');
    $sale = makeVehicleSaleRecord($vehicle, $user, ['sale_status' => 'sold']);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.update', [$vehicle->id, $sale->id]), validVehicleSalePayload(['sale_status' => 'reserved']))
        ->assertStatus(422)
        ->assertJsonPath('message', '已成交銷售紀錄不可改回保留狀態。');
});

it('同車不可建立多筆 active sale 避免生命週期互相覆寫', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-active-conflict@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-CONFLICT-001', 'vin-sale-conflict-001');
    makeVehicleSaleRecord($vehicle, $user, ['sale_status' => 'reserved']);

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload(['sale_status' => 'sold']))
        ->assertStatus(422)
        ->assertJsonPath('message', '此車已有進行中的銷售紀錄。');
});

it('Show/Edit 在無 sales.view costs.view pricing.view 時不回傳銷售成本價格或毛利 payload', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-isolation-deny@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.update');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-ISOLATION-001', 'vin-sale-isolation-001');
    $vehicle->update(['asking_price' => 980000, 'floor_price' => 900000]);
    makeVehicleSaleRecord($vehicle, $user, ['customer_phone' => 'secret-phone', 'commission_amount' => 99999]);

    foreach (['show', 'edit'] as $action) {
        $this->actingAs($user)
            ->get(route('employee-system.vehicles.'.$action, $vehicle->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('vehicleSales')
                ->missing('vehicleSaleSummary')
                ->missing('vehicle.asking_price')
                ->missing('vehicle.floor_price')
                ->where('vehicleCosts', null)
                ->where('vehicleCostSummary', null)
                ->missing('vehicleSales.0.sale_price')
                ->missing('vehicleSales.0.customer_phone')
                ->missing('vehicleSales.0.commission_amount')
                ->missing('vehicleSales.0.completion')
                ->missing('vehicleSales.0.gross_profit')
                ->missing('vehicleSales.0.gross_margin')
                ->missing('vehicleSales.0.profit')
            );
    }
});

it('sales 資料不暴露成本或毛利欄位', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-no-profit@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.view');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-NOPROFIT-001', 'vin-sale-noprofit-001');
    makeVehicleSaleRecord($vehicle, $user);

    $this->actingAs($user)
        ->get(route('employee-system.vehicles.show', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('vehicleSales.0.cost_amount')
            ->missing('vehicleSales.0.gross_profit')
            ->missing('vehicleSales.0.gross_margin')
            ->missing('vehicleSales.0.profit')
            ->missing('vehicleSaleSummary.gross_profit')
            ->missing('vehicleSaleSummary.gross_margin')
            ->missing('vehicleSaleSummary.profit')
            ->missing('vehicleSales.0.company_id')
            ->missing('vehicleSales.0.branch_id')
            ->missing('vehicleSales.0.vehicle_id')
            ->missing('vehicleSales.0.completion.company_id')
            ->missing('vehicleSales.0.completion.branch_id')
            ->missing('vehicleSales.0.completion.completed_by')
            ->missing('vehicleSales.0.completion.completed_by_email')
            ->missing('vehicleSales.0.completion.accounting_event_id')
            ->missing('vehicleSales.0.completion.journal_entry_id')
            ->missing('vehicleSales.0.completion.gross_profit')
            ->missing('vehicleSales.0.completion.gross_margin')
            ->missing('vehicleSales.0.completion.profit')
            ->missing('vehicleSales.0.completion.revenue_amount')
            ->missing('vehicleSales.0.completion.cogs_amount')
        );
});

it('audit log 有 vehicle_sale.created / vehicle_sale.updated 且不記系統欄位', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-audit@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $user->givePermissionTo('module.vehicles.sales.update');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-AUDIT-001', 'vin-sale-audit-001');

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload(['notes' => 'created audit']))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $createdLog = ActivityLog::query()->where('event', 'vehicle_sale.created')->latest('id')->first();
    expect($createdLog)->not->toBeNull()
        ->and($createdLog?->new_values['notes'] ?? null)->toBe('created audit')
        ->and(array_key_exists('company_id', $createdLog?->new_values ?? []))->toBeFalse()
        ->and(array_key_exists('created_by', $createdLog?->new_values ?? []))->toBeFalse();

    $sale = VehicleSale::query()->latest('id')->firstOrFail();
    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.update', [$vehicle->id, $sale->id]), validVehicleSalePayload(['notes' => 'updated audit']))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $updatedLog = ActivityLog::query()->where('event', 'vehicle_sale.updated')->latest('id')->first();
    expect($updatedLog)->not->toBeNull()
        ->and($updatedLog?->old_values['notes'] ?? null)->toBe('created audit')
        ->and($updatedLog?->new_values['notes'] ?? null)->toBe('updated audit')
        ->and(array_key_exists('updated_by', $updatedLog?->new_values ?? []))->toBeFalse();
});

it('audit log 可記 customer_id 但不記 Customer 敏感個資欄位', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-customer-audit@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $user->givePermissionTo('module.vehicles.sales.create');
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-CUS-AUDIT-001', 'vin-sale-cus-audit-001');
    $customer = makeVehicleSaleCustomer(1, 10, 'CU-202606-0004', [
        'name' => '稽核主檔客戶',
        'phone' => '0922222333',
    ]);

    $this->actingAs($user)
        ->post(route('employee-system.vehicles.sales.store', $vehicle->id), validVehicleSalePayload([
            'customer_id' => $customer->id,
        ]))
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $createdLog = ActivityLog::query()->where('event', 'vehicle_sale.created')->latest('id')->first();

    expect($createdLog)->not->toBeNull()
        ->and($createdLog?->new_values['customer_id'] ?? null)->toBe($customer->id)
        ->and($createdLog?->new_values['customer_name'] ?? null)->toBe('稽核主檔客戶')
        ->and($createdLog?->new_values['customer_phone'] ?? null)->toBe('0922222333')
        ->and(array_key_exists('id_number', $createdLog?->new_values ?? []))->toBeFalse()
        ->and(array_key_exists('birthday', $createdLog?->new_values ?? []))->toBeFalse()
        ->and(array_key_exists('address', $createdLog?->new_values ?? []))->toBeFalse();
});

it('有 completion.confirm 權限且符合條件可完成交易並寫入 audit log', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-complete-allow@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-COMP-ACT-001', 'vin-sale-comp-act-001');
    $vehicle->update(['lifecycle_status' => 'sold']);
    $sale = makeVehicleSaleRecord($vehicle, $user, [
        'sale_price' => 760000,
        'paid_amount' => 123,
        'sale_status' => 'sold',
        'sold_at' => now()->subDay(),
        'completed_at' => null,
    ]);
    $payment = makeVehicleSalePaymentRecord($sale, $user, 760000);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]), [
            'completion_note' => '交車完成，文件確認。',
        ])
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $sale->refresh();
    expect($sale->completed_at)->not->toBeNull()
        ->and($sale->completed_by)->toBe($user->id)
        ->and($sale->completion_note)->toBe('交車完成，文件確認。')
        ->and($sale->sale_status)->toBe('sold')
        ->and($sale->sale_price)->toBe('760000.00')
        ->and($sale->paid_amount)->toBe('123.00')
        ->and($vehicle->fresh()->lifecycle_status)->toBe('sold')
        ->and($payment->fresh()->amount)->toBe('760000.00');

    $audit = ActivityLog::query()->where('event', 'vehicle_sale.transaction_completed')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit?->metadata['module'] ?? null)->toBe('vehicle_sales')
        ->and($audit?->description)->toBe('Vehicle sale transaction completed')
        ->and($audit?->new_values['completion_note'] ?? null)->toBe('交車完成，文件確認。')
        ->and($audit?->new_values['receivable_status'] ?? null)->toBe('paid');
});

it('沒有 completion.confirm 權限不可完成交易且其他銷售或收款權限不可替代', function (): void {
    foreach ([['module.vehicles.sales.completion.view'], ['module.vehicles.sales.update'], ['module.receivables.mark-sold']] as $index => $permissions) {
        $user = makeVehicleSaleUser('vehicle-sale-complete-deny-'.$index.'@example.com');
        $user->givePermissionTo(array_merge(['module.vehicles.view'], $permissions));
        $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-COMP-DENY-'.$index, 'vin-sale-comp-deny-'.$index);
        $vehicle->update(['lifecycle_status' => 'sold']);
        $sale = makeVehicleSaleRecord($vehicle, $user, ['sale_price' => 100000, 'sale_status' => 'sold', 'sold_at' => now()]);
        makeVehicleSalePaymentRecord($sale, $user, 100000);

        $this->actingAs($user)
            ->patch(route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]))
            ->assertForbidden();

        expect($sale->fresh()->completed_at)->toBeNull();
    }
});

it('跨 tenant 不可完成交易且優先回 404', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-complete-cross@example.com', 1, 10);
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $crossUser = makeVehicleSaleUser('vehicle-sale-complete-cross-owner@example.com', 2, 20);
    $crossVehicle = makeVehicleSaleVehicle(2, 20, 'STK-SALE-COMP-XTEN-001', 'vin-sale-comp-xten-001');
    $crossVehicle->update(['lifecycle_status' => 'sold']);
    $crossSale = makeVehicleSaleRecord($crossVehicle, $crossUser, ['sale_price' => 100000, 'sale_status' => 'sold', 'sold_at' => now()]);
    makeVehicleSalePaymentRecord($crossSale, $crossUser, 100000);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.complete', [$crossVehicle->id, $crossSale->id]))
        ->assertNotFound();

    expect($crossSale->fresh()->completed_at)->toBeNull();
});

it('收款未完成或部分收款不可完成交易', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-complete-unpaid@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);

    foreach ([[0, '收款尚未完成，無法完成交易。'], [50000, '收款尚未完成，無法完成交易。']] as $index => [$paid, $message]) {
        $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-COMP-UNPAID-'.$index, 'vin-sale-comp-unpaid-'.$index);
        $vehicle->update(['lifecycle_status' => 'sold']);
        $sale = makeVehicleSaleRecord($vehicle, $user, ['sale_price' => 100000, 'sale_status' => 'sold', 'sold_at' => now()]);
        if ($paid > 0) {
            makeVehicleSalePaymentRecord($sale, $user, $paid);
        }

        $this->actingAs($user)
            ->patch(route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]))
            ->assertStatus(422)
            ->assertJson(['message' => $message]);

        expect($sale->fresh()->completed_at)->toBeNull();
    }
});

it('reserved sale 不可完成交易', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-complete-reserved@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-COMP-RES-001', 'vin-sale-comp-res-001');
    $vehicle->update(['lifecycle_status' => 'sold']);
    $sale = makeVehicleSaleRecord($vehicle, $user, ['sale_price' => 100000, 'sale_status' => 'reserved']);
    makeVehicleSalePaymentRecord($sale, $user, 100000);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]))
        ->assertStatus(422)
        ->assertJson(['message' => '僅已成交銷售可完成交易。']);

    expect($sale->fresh()->completed_at)->toBeNull();
});

it('車輛不是 sold 不可完成交易', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-complete-vehicle-unsold@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);

    foreach (['in_stock', 'reserved'] as $index => $status) {
        $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-COMP-VEH-'.$index, 'vin-sale-comp-veh-'.$index);
        $vehicle->update(['lifecycle_status' => $status]);
        $sale = makeVehicleSaleRecord($vehicle, $user, ['sale_price' => 100000, 'sale_status' => 'sold', 'sold_at' => now()]);
        makeVehicleSalePaymentRecord($sale, $user, 100000);

        $this->actingAs($user)
            ->patch(route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]))
            ->assertStatus(422)
            ->assertJson(['message' => '僅已售出車輛可完成交易。']);

        expect($sale->fresh()->completed_at)->toBeNull();
    }
});

it('已完成交易不可重複 complete 且不覆寫完成欄位', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-complete-repeat@example.com');
    $other = makeVehicleSaleUser('vehicle-sale-complete-repeat-other@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-COMP-REPEAT-001', 'vin-sale-comp-repeat-001');
    $vehicle->update(['lifecycle_status' => 'sold']);
    $completedAt = now()->subDays(2)->setMicrosecond(0);
    $sale = makeVehicleSaleRecord($vehicle, $user, [
        'sale_price' => 100000,
        'sale_status' => 'sold',
        'sold_at' => now(),
        'completed_at' => $completedAt,
        'completed_by' => $other->id,
        'completion_note' => '原始完成備註',
    ]);
    makeVehicleSalePaymentRecord($sale, $user, 100000);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]), ['completion_note' => '不應覆寫'])
        ->assertStatus(422)
        ->assertJson(['message' => '此交易已完成，不可重複完成。']);

    $sale->refresh();
    expect($sale->completed_at?->toDateTimeString())->toBe($completedAt->toDateTimeString())
        ->and($sale->completed_by)->toBe($other->id)
        ->and($sale->completion_note)->toBe('原始完成備註');
});

it('complete payload 夾帶系統、會計、收入、COGS 或毛利欄位時拒絕', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-complete-payload-deny@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-COMP-PAYLOAD-001', 'vin-sale-comp-payload-001');
    $vehicle->update(['lifecycle_status' => 'sold']);
    $sale = makeVehicleSaleRecord($vehicle, $user, ['sale_price' => 100000, 'sale_status' => 'sold', 'sold_at' => now()]);
    makeVehicleSalePaymentRecord($sale, $user, 100000);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]), [
            'completion_note' => 'payload guard',
            'company_id' => 999,
            'branch_id' => 888,
            'completed_by' => 777,
            'completed_at' => now()->subYear()->toDateTimeString(),
            'sale_status' => 'cancelled',
            'accounting_event_id' => 1,
            'journal_entry_id' => 2,
            'revenue_amount' => 100000,
            'cogs_amount' => 80000,
            'gross_profit' => 20000,
            'gross_margin' => 20,
        ])
        ->assertForbidden();

    expect($sale->fresh()->completed_at)->toBeNull();
});

it('complete audit 僅包含安全白名單且排除 tenant 個資會計與毛利欄位', function (): void {
    $user = makeVehicleSaleUser('vehicle-sale-complete-audit-white@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = makeVehicleSaleVehicle(1, 10, 'STK-SALE-COMP-AUDIT-001', 'vin-sale-comp-audit-001');
    $vehicle->update(['lifecycle_status' => 'sold']);
    $customer = makeVehicleSaleCustomer(1, 10, 'CU-202606-COMP', [
        'name' => '完成交易客戶',
        'phone' => '0999999999',
        'id_number' => 'B123456789',
        'birthday' => '1988-08-08',
        'address' => '不可寫入 audit 的地址',
    ]);
    $sale = makeVehicleSaleRecord($vehicle, $user, [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'sale_price' => 100000,
        'sale_status' => 'sold',
        'sold_at' => now(),
    ]);
    makeVehicleSalePaymentRecord($sale, $user, 120000);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]), ['completion_note' => 'audit whitelist'])
        ->assertRedirect();

    $audit = ActivityLog::query()->where('event', 'vehicle_sale.transaction_completed')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit?->old_values)->toHaveKeys(['completed_at', 'completed_by', 'completion_note', 'receivable_status', 'vehicle_stock_number'])
        ->and($audit?->new_values)->toHaveKeys(['completed_at', 'completed_by', 'completion_note', 'receivable_status', 'vehicle_stock_number'])
        ->and($audit?->new_values['vehicle_stock_number'] ?? null)->toBe('STK-SALE-COMP-AUDIT-001')
        ->and($audit?->new_values['receivable_status'] ?? null)->toBe('overpaid')
        ->and($audit?->new_values['customer_name'] ?? null)->toBe('完成交易客戶');

    foreach (['company_id', 'branch_id', 'customer_phone', 'id_number', 'birthday', 'address', 'gross_profit', 'gross_margin', 'profit', 'accounting_event_id', 'journal_entry_id'] as $forbidden) {
        expect(array_key_exists($forbidden, $audit?->old_values ?? []))->toBeFalse()
            ->and(array_key_exists($forbidden, $audit?->new_values ?? []))->toBeFalse();
    }
});

it('Staff Permission 權限矩陣可看到 vehicles.sales nested permission', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('permissionMatrix', function ($matrix): bool {
                $matrix = is_array($matrix) ? $matrix : $matrix->all();

                return isset($matrix['vehicles.sales'])
                    && ($matrix['vehicles.sales']['label'] ?? null) === '車輛銷售'
                    && ($matrix['vehicles.sales']['actions']['view']['permission'] ?? null) === 'module.vehicles.sales.view'
                    && ($matrix['vehicles.sales']['actions']['create']['permission'] ?? null) === 'module.vehicles.sales.create'
                    && ($matrix['vehicles.sales']['actions']['update']['permission'] ?? null) === 'module.vehicles.sales.update';
            })
        );
});
