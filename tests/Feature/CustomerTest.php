<?php

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Module;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Module::updateOrCreate(
        ['key' => 'customers'],
        [
            'label' => '客戶管理',
            'section' => 'operations',
            'route_name' => 'employee-system.customers.index',
            'base_permission' => 'module.customers.view',
            'permission_prefix' => 'module.customers',
            'icon_key' => 'employees',
            'sort_order' => 35,
            'is_enabled' => true,
            'is_active' => true,
            'active_patterns' => ['employee-system.customers.*'],
        ]
    );

    foreach ([
        'module.customers.view',
        'module.customers.create',
        'module.customers.update',
        'module.customers.sensitive.view',
        'module.customers.sensitive.update',
        'module.vehicles.sales.view',
        'module.receivables.view',
        'staff-permission.view',
        'staff-permission.update-permission',
        'module.permissions.view',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

function ensureCustomerTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        ['name' => 'Company '.$companyId, 'code' => 'C'.$companyId, 'created_at' => now(), 'updated_at' => now()]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            ['company_id' => $companyId, 'name' => 'Branch '.$branchId, 'code' => 'B'.$companyId.'-'.$branchId, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}

function makeCustomerVehicle(User $user, string $stockNumber = 'CUS-TXN-001'): Vehicle
{
    return Vehicle::create([
        'company_id' => (int) $user->company_id,
        'branch_id' => (int) $user->branch_id,
        'stock_number' => $stockNumber,
        'vin' => $stockNumber.'VIN',
        'license_plate' => 'TXN-001',
        'brand' => 'Toyota',
        'model' => 'RAV4',
        'model_year' => 2024,
        'lifecycle_status' => 'sold',
    ]);
}

function makeCustomerSale(Customer $customer, Vehicle $vehicle, User $user, array $overrides = []): VehicleSale
{
    return VehicleSale::create(array_merge([
        'company_id' => (int) $customer->company_id,
        'branch_id' => (int) $customer->branch_id,
        'vehicle_id' => $vehicle->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'sale_price' => 100000,
        'sale_status' => 'sold',
        'sold_at' => now()->toDateString(),
        'salesperson_name' => '業務甲',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

function makeCustomerPayment(VehicleSale $sale, User $user, float $amount, string $status = 'received'): VehicleSalePayment
{
    return VehicleSalePayment::create([
        'company_id' => (int) $sale->company_id,
        'branch_id' => (int) $sale->branch_id,
        'vehicle_id' => (int) $sale->vehicle_id,
        'vehicle_sale_id' => (int) $sale->id,
        'customer_id' => $sale->customer_id,
        'payment_number' => 'CUS-PAY-'.uniqid(),
        'payment_type' => 'deposit',
        'payment_method' => 'cash',
        'amount' => $amount,
        'paid_at' => now()->toDateString(),
        'status' => $status,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
}

function makeCustomerUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    ensureCustomerTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'Customer User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function makeCustomerRecord(int $companyId = 1, int $branchId = 10, array $overrides = []): Customer
{
    ensureCustomerTenantRows($companyId, $branchId);

    return Customer::create(array_merge([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'customer_number' => 'CU-202606-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'name' => '王小明',
        'phone' => '0911000000',
        'secondary_phone' => '0223456789',
        'email' => 'customer@example.com',
        'line_id' => 'line-customer',
        'id_number' => 'A123456789',
        'birthday' => '1990-01-02',
        'address' => '台北市信義區',
        'status' => 'lead',
        'source' => 'walk-in',
        'notes' => '一般備註',
    ], $overrides));
}

function validCustomerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => '陳客戶',
        'phone' => '0988123456',
        'secondary_phone' => '0222223333',
        'email' => 'new-customer@example.com',
        'line_id' => 'new-line',
        'status' => 'active',
        'source' => 'website',
        'notes' => '客戶備註',
    ], $overrides);
}

it('有 customers.view 權限者可看 index', function (): void {
    $user = makeCustomerUser('customer-index-allow@example.com');
    $user->givePermissionTo('module.customers.view');

    $this->actingAs($user)->get(route('employee-system.customers.index'))->assertOk();
});

it('沒有 customers.view 權限者 index 回 403', function (): void {
    $user = makeCustomerUser('customer-index-deny@example.com');

    $this->actingAs($user)->get(route('employee-system.customers.index'))->assertForbidden();
});

it('有 customers.create 可建立且 customer_number 由後端產生', function (): void {
    $user = makeCustomerUser('customer-create-allow@example.com');
    $user->givePermissionTo('module.customers.view', 'module.customers.create');

    $this->actingAs($user)
        ->post(route('employee-system.customers.store'), validCustomerPayload())
        ->assertRedirect();

    $customer = Customer::query()->firstOrFail();
    expect($customer->customer_number)->toMatch('/^CU-\d{6}-0001$/')
        ->and($customer->company_id)->toBe(1)
        ->and($customer->branch_id)->toBe(10)
        ->and($customer->created_by)->toBe($user->id)
        ->and($customer->updated_by)->toBe($user->id);
});

it('沒有 customers.create 建立回 403', function (): void {
    $user = makeCustomerUser('customer-create-deny@example.com');
    $user->givePermissionTo('module.customers.view');

    $this->actingAs($user)
        ->post(route('employee-system.customers.store'), validCustomerPayload())
        ->assertForbidden();
});

it('有 customers.update 可更新', function (): void {
    $user = makeCustomerUser('customer-update-allow@example.com');
    $user->givePermissionTo('module.customers.view', 'module.customers.update');
    $customer = makeCustomerRecord();

    $this->actingAs($user)
        ->patch(route('employee-system.customers.update', $customer), validCustomerPayload(['name' => '更新後客戶']))
        ->assertRedirect(route('employee-system.customers.show', $customer->id));

    expect($customer->fresh()->name)->toBe('更新後客戶');
});

it('沒有 customers.update 更新回 403', function (): void {
    $user = makeCustomerUser('customer-update-deny@example.com');
    $user->givePermissionTo('module.customers.view');
    $customer = makeCustomerRecord();

    $this->actingAs($user)
        ->patch(route('employee-system.customers.update', $customer), validCustomerPayload(['name' => '不應更新']))
        ->assertForbidden();
});

it('前端傳入 customer_number 或系統欄位會被拒絕', function (): void {
    $user = makeCustomerUser('customer-system-field-deny@example.com');
    $user->givePermissionTo('module.customers.view', 'module.customers.create');

    foreach (['customer_number', 'company_id', 'branch_id', 'created_by', 'updated_by'] as $field) {
        $this->actingAs($user)
            ->post(route('employee-system.customers.store'), validCustomerPayload([$field => $field === 'customer_number' ? 'CU-HACK' : 999]))
            ->assertForbidden();
    }
});

it('跨 company 或 branch 的 show edit update 回 404', function (): void {
    $user = makeCustomerUser('customer-cross-tenant@example.com', 1, 10);
    $user->givePermissionTo('module.customers.view', 'module.customers.update');
    $crossCompany = makeCustomerRecord(2, 10);
    $crossBranch = makeCustomerRecord(1, 99);

    $this->actingAs($user)->get(route('employee-system.customers.show', $crossCompany))->assertNotFound();
    $this->actingAs($user)->get(route('employee-system.customers.edit', $crossBranch))->assertNotFound();
    $this->actingAs($user)->patch(route('employee-system.customers.update', $crossBranch), validCustomerPayload())->assertNotFound();
});

it('沒有 sensitive.view 時 show edit payload 不回傳敏感欄位', function (): void {
    $user = makeCustomerUser('customer-sensitive-view-deny@example.com');
    $user->givePermissionTo('module.customers.view', 'module.customers.update');
    $customer = makeCustomerRecord();

    $this->actingAs($user)
        ->get(route('employee-system.customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('customer.id_number')
            ->missing('customer.birthday')
            ->missing('customer.address')
        );

    $this->actingAs($user)
        ->get(route('employee-system.customers.edit', $customer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('customer.id_number')
            ->missing('customer.birthday')
            ->missing('customer.address')
        );
});

it('有 sensitive.view 時 show edit payload 可回傳敏感欄位', function (): void {
    $user = makeCustomerUser('customer-sensitive-view-allow@example.com');
    $user->givePermissionTo('module.customers.view', 'module.customers.update', 'module.customers.sensitive.view');
    $customer = makeCustomerRecord();

    $this->actingAs($user)
        ->get(route('employee-system.customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('customer.id_number', 'A123456789')
            ->where('customer.birthday', '1990-01-02')
            ->where('customer.address', '台北市信義區')
        );

    $this->actingAs($user)
        ->get(route('employee-system.customers.edit', $customer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('customer.id_number', 'A123456789'));
});

it('有 vehicles.sales.view 時 Customer Show 回傳 customer_id 關聯交易並排除 snapshot-only 與跨 tenant', function (): void {
    $user = makeCustomerUser('customer-transaction-view@example.com');
    $user->givePermissionTo('module.customers.view', 'module.vehicles.sales.view');
    $customer = makeCustomerRecord();
    $sale = makeCustomerSale($customer, makeCustomerVehicle($user), $user);

    makeCustomerSale($customer, makeCustomerVehicle($user, 'CUS-SNAPSHOT'), $user, ['customer_id' => null, 'customer_name' => $customer->name, 'customer_phone' => $customer->phone]);

    $crossUser = makeCustomerUser('customer-transaction-cross@example.com', 2, 20);
    $crossCustomer = makeCustomerRecord(2, 20, ['name' => $customer->name, 'phone' => $customer->phone]);
    makeCustomerSale($crossCustomer, makeCustomerVehicle($crossUser, 'CUS-CROSS'), $crossUser, ['customer_id' => $customer->id]);

    $this->actingAs($user)
        ->get(route('employee-system.customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('customerTransactions', 1)
            ->where('customerTransactions.0.id', $sale->id)
            ->where('customerTransactions.0.vehicle.stock_number', 'CUS-TXN-001')
            ->where('customerTransactions.0.sale_price', '100000.00')
            ->where('can.view_customer_transactions', true)
            ->missing('customerTransactions.0.company_id')
            ->missing('customerTransactions.0.branch_id')
            ->missing('customerTransactions.0.vehicle_id')
            ->missing('customerTransactions.0.customer_id')
            ->missing('customerTransactions.0.vehicle.id')
        );
});

it('沒有 vehicles.sales.view 時 Customer Show 不回傳 customerTransactions', function (): void {
    $user = makeCustomerUser('customer-transaction-deny@example.com');
    $user->givePermissionTo('module.customers.view');
    $customer = makeCustomerRecord();
    makeCustomerSale($customer, makeCustomerVehicle($user), $user);

    $this->actingAs($user)
        ->get(route('employee-system.customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('customerTransactions', null)
            ->where('can.view_customer_transactions', false)
        );
});

it('有 receivables.view 時回傳 ReceivableSummaryService 摘要且 voided 不計入已收', function (): void {
    $user = makeCustomerUser('customer-transaction-receivable@example.com');
    $user->givePermissionTo('module.customers.view', 'module.vehicles.sales.view', 'module.receivables.view');
    $customer = makeCustomerRecord();
    $sale = makeCustomerSale($customer, makeCustomerVehicle($user), $user, ['sale_price' => 1000]);
    makeCustomerPayment($sale, $user, 400, 'received');
    makeCustomerPayment($sale, $user, 600, 'voided');

    $this->actingAs($user)
        ->get(route('employee-system.customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('customerTransactions.0.receivable_summary.receivable_amount', '1000.00')
            ->where('customerTransactions.0.receivable_summary.received_amount', '400.00')
            ->where('customerTransactions.0.receivable_summary.receivable_balance', '600.00')
            ->where('customerTransactions.0.receivable_summary.receivable_status', 'partial')
            ->where('customerTransactions.0.receivable_summary.received_payment_count', 1)
            ->where('customerTransactions.0.receivable_summary.payment_record_count', 2)
            ->where('can.view_customer_transaction_receivables', true)
            ->has('customerTransactions.0.links.receivable_show_url')
        );
});

it('無 receivables.view 時不回傳 receivable_summary 且不暴露財務衍生或成本毛利欄位', function (): void {
    $user = makeCustomerUser('customer-transaction-receivable-deny@example.com');
    $user->givePermissionTo('module.customers.view', 'module.vehicles.sales.view');
    $customer = makeCustomerRecord();
    $sale = makeCustomerSale($customer, makeCustomerVehicle($user), $user);
    makeCustomerPayment($sale, $user, 100, 'received');

    $this->actingAs($user)
        ->get(route('employee-system.customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('customerTransactions.0.receivable_summary')
            ->missing('customerTransactions.0.links.receivable_show_url')
            ->missing('customerTransactions.0.profit')
            ->missing('customerTransactions.0.gross_profit')
            ->missing('customerTransactions.0.gross_margin')
            ->missing('customerTransactions.0.margin')
            ->missing('customerTransactions.0.cost')
            ->missing('customerTransactions.0.payments')
            ->where('can.view_customer_transaction_receivables', false)
        );
});

it('沒有 sensitive.update 時建立或更新敏感欄位回 403', function (): void {
    $user = makeCustomerUser('customer-sensitive-update-deny@example.com');
    $user->givePermissionTo('module.customers.view', 'module.customers.create', 'module.customers.update');
    $customer = makeCustomerRecord();

    $this->actingAs($user)
        ->post(route('employee-system.customers.store'), validCustomerPayload(['id_number' => 'B123456789']))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('employee-system.customers.update', $customer), validCustomerPayload(['address' => '新地址']))
        ->assertForbidden();
});

it('有 sensitive.update 時可建立或更新敏感欄位', function (): void {
    $user = makeCustomerUser('customer-sensitive-update-allow@example.com');
    $user->givePermissionTo('module.customers.view', 'module.customers.create', 'module.customers.update', 'module.customers.sensitive.update');

    $this->actingAs($user)
        ->post(route('employee-system.customers.store'), validCustomerPayload([
            'id_number' => 'B123456789',
            'birthday' => '1988-03-04',
            'address' => '高雄市',
        ]))
        ->assertRedirect();

    $customer = Customer::query()->firstOrFail();
    expect($customer->id_number)->toBe('B123456789');

    $this->actingAs($user)
        ->patch(route('employee-system.customers.update', $customer), validCustomerPayload(['address' => '台中市']))
        ->assertRedirect();

    expect($customer->fresh()->address)->toBe('台中市');
});

it('index 搜尋 q 可搜尋 name phone customer_number 且 status filter 可篩選', function (): void {
    $user = makeCustomerUser('customer-search-filter@example.com');
    $user->givePermissionTo('module.customers.view');
    makeCustomerRecord(1, 10, ['customer_number' => 'CU-202606-0101', 'name' => '搜尋目標', 'phone' => '0911222333', 'status' => 'lead']);
    makeCustomerRecord(1, 10, ['customer_number' => 'CU-202606-0102', 'name' => '其他客戶', 'phone' => '0944555666', 'status' => 'archived']);

    $this->actingAs($user)
        ->get(route('employee-system.customers.index', ['q' => '搜尋目標']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('customers.data', fn ($rows): bool => count($rows) === 1 && $rows[0]['name'] === '搜尋目標'));

    $this->actingAs($user)
        ->get(route('employee-system.customers.index', ['q' => '0911222333']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('customers.data.0.customer_number', 'CU-202606-0101'));

    $this->actingAs($user)
        ->get(route('employee-system.customers.index', ['q' => '0102', 'status' => 'archived']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('customers.data.0.status', 'archived'));
});

it('audit log 有 created updated 且不記敏感欄位', function (): void {
    $user = makeCustomerUser('customer-audit@example.com');
    $user->givePermissionTo('module.customers.view', 'module.customers.create', 'module.customers.update', 'module.customers.sensitive.update');

    $this->actingAs($user)
        ->post(route('employee-system.customers.store'), validCustomerPayload(['id_number' => 'C123456789', 'address' => '敏感地址']))
        ->assertRedirect();

    $customer = Customer::query()->firstOrFail();
    $this->actingAs($user)
        ->patch(route('employee-system.customers.update', $customer), validCustomerPayload(['name' => '稽核更新', 'birthday' => '1999-09-09']))
        ->assertRedirect();

    expect(ActivityLog::query()->where('event', 'customer.created')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'customer.updated')->exists())->toBeTrue();

    $encoded = ActivityLog::query()
        ->whereIn('event', ['customer.created', 'customer.updated'])
        ->get(['old_values', 'new_values'])
        ->toJson();
    expect($encoded)->not->toContain('id_number')
        ->and($encoded)->not->toContain('birthday')
        ->and($encoded)->not->toContain('address')
        ->and($encoded)->not->toContain('C123456789')
        ->and($encoded)->not->toContain('敏感地址');
});

it('Staff Permission matrix 可看到 customers.sensitive nested permission', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('permissionMatrix', function ($matrix): bool {
                $matrix = is_array($matrix) ? $matrix : $matrix->all();

                return isset($matrix['customers'])
                    && ($matrix['customers']['label'] ?? null) === '客戶管理'
                    && ($matrix['customers']['actions']['view']['permission'] ?? null) === 'module.customers.view'
                    && isset($matrix['customers.sensitive'])
                    && ($matrix['customers.sensitive']['label'] ?? null) === '客戶個資'
                    && ($matrix['customers.sensitive']['actions']['view']['permission'] ?? null) === 'module.customers.sensitive.view'
                    && ($matrix['customers.sensitive']['actions']['update']['permission'] ?? null) === 'module.customers.sensitive.update';
            })
        );
});
