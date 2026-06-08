<?php

use App\Models\AccountingEvent;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalEntryLine;
use App\Models\Customer;
use App\Models\Module;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;
use App\Services\AccountingEventService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function aeCompletionEnsureTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'AE Completion Company '.$companyId,
            'code' => 'AEC'.$companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            [
                'company_id' => $companyId,
                'name' => 'AE Completion Branch '.$branchId,
                'code' => 'AECB'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function aeCompletionRegisterVehiclesModule(): void
{
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
    Permission::findOrCreate('module.vehicles.sales.completion.confirm', 'web');
}

function aeCompletionRegisterAccountingEventsModule(): void
{
    Module::updateOrCreate(
        ['key' => 'accounting-events'],
        [
            'label' => '會計事件',
            'section' => 'accounting',
            'route_name' => 'employee-system.accounting.events.index',
            'base_permission' => 'module.accounting.events.view',
            'permission_prefix' => 'module.accounting.events',
            'icon_key' => 'Receipt',
            'icon' => 'Receipt',
            'sort_order' => 41,
            'is_enabled' => true,
            'is_active' => true,
            'active_patterns' => ['employee-system.accounting.events.*'],
        ]
    );

    Permission::findOrCreate('module.accounting.events.view', 'web');
}

function aeCompletionMakeUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    aeCompletionEnsureTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'AE Completion User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function aeCompletionMakeVehicle(int $companyId, int $branchId, string $stock, string $vin, string $status = 'sold'): Vehicle
{
    aeCompletionEnsureTenantRows($companyId, $branchId);

    return Vehicle::create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'stock_number' => $stock,
        'vin' => $vin,
        'brand' => 'Toyota',
        'model' => 'Camry',
        'variant' => 'Hybrid',
        'model_year' => 2023,
        'lifecycle_status' => $status,
    ]);
}

function aeCompletionMakeCustomer(int $companyId, int $branchId, string $number, array $overrides = []): Customer
{
    aeCompletionEnsureTenantRows($companyId, $branchId);

    return Customer::create(array_merge([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'customer_number' => $number,
        'name' => '安全摘要客戶',
        'phone' => '0999888777',
        'id_number' => 'A123456789',
        'birthday' => '1990-01-01',
        'address' => '敏感地址',
        'status' => 'active',
    ], $overrides));
}

function aeCompletionMakeSale(Vehicle $vehicle, User $actor, array $overrides = []): VehicleSale
{
    return VehicleSale::create(array_merge([
        'company_id' => $vehicle->company_id,
        'branch_id' => $vehicle->branch_id,
        'vehicle_id' => $vehicle->id,
        'customer_id' => null,
        'customer_name' => 'Completion Customer',
        'customer_phone' => '0900000000',
        'sale_price' => 100000,
        'deposit_amount' => 0,
        'paid_amount' => 999999,
        'sale_status' => 'sold',
        'sold_at' => '2026-06-08 10:00:00',
        'salesperson_name' => 'Sales',
        'commission_amount' => 0,
        'completed_at' => null,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ], $overrides));
}

function aeCompletionMakePayment(VehicleSale $sale, User $actor, float $amount, string $status = 'received'): VehicleSalePayment
{
    return VehicleSalePayment::create([
        'company_id' => $sale->company_id,
        'branch_id' => $sale->branch_id,
        'vehicle_id' => $sale->vehicle_id,
        'vehicle_sale_id' => $sale->id,
        'customer_id' => $sale->customer_id,
        'payment_number' => 'PAY-AEC-'.uniqid(),
        'payment_type' => 'final_payment',
        'payment_method' => 'cash',
        'amount' => $amount,
        'paid_at' => '2026-06-08',
        'status' => $status,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);
}

function aeCompletionCompleteRoute(Vehicle $vehicle, VehicleSale $sale): string
{
    return route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]);
}

it('successful completion creates pending accounting event', function (): void {
    aeCompletionRegisterVehiclesModule();
    $user = aeCompletionMakeUser('aec-success@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = aeCompletionMakeVehicle(1, 10, 'STK-AEC-SUCCESS-001', 'vin-aec-success-001');
    $sale = aeCompletionMakeSale($vehicle, $user, ['sale_price' => 100000]);
    aeCompletionMakePayment($sale, $user, 120000);

    $this->actingAs($user)
        ->patch(aeCompletionCompleteRoute($vehicle, $sale), ['completion_note' => 'phase 3 completion'])
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $sale->refresh();
    $event = AccountingEvent::query()->firstOrFail();

    expect($sale->completed_at)->not->toBeNull()
        ->and(AccountingEvent::count())->toBe(1)
        ->and($event->company_id)->toBe($sale->company_id)
        ->and($event->branch_id)->toBe($sale->branch_id)
        ->and($event->source_type)->toBe('vehicle_sale_completion')
        ->and($event->source_id)->toBe($sale->id)
        ->and($event->source_number)->toBe('STK-AEC-SUCCESS-001')
        ->and($event->event_type)->toBe('vehicle_sale_completed')
        ->and($event->status)->toBe('pending')
        ->and($event->currency)->toBe('TWD')
        ->and($event->amount)->toBe('100000.00')
        ->and($event->created_by)->toBe($user->id)
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and($event->reviewed_by)->toBeNull()
        ->and($event->voided_by)->toBeNull()
        ->and($event->voided_at)->toBeNull()
        ->and($event->void_reason)->toBeNull();
});

it('completion accounting event payload uses safe allowlist', function (): void {
    aeCompletionRegisterVehiclesModule();
    $user = aeCompletionMakeUser('aec-payload@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = aeCompletionMakeVehicle(1, 10, 'STK-AEC-PAYLOAD-001', 'vin-aec-payload-001');
    $customer = aeCompletionMakeCustomer(1, 10, 'CU-AEC-001');
    $sale = aeCompletionMakeSale($vehicle, $user, [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
    ]);
    aeCompletionMakePayment($sale, $user, 100000);

    $this->actingAs($user)->patch(aeCompletionCompleteRoute($vehicle, $sale))->assertRedirect();

    $payload = AccountingEvent::query()->firstOrFail()->payload;

    expect($payload)->toHaveKeys(['vehicle_stock_number', 'customer_id', 'customer_number', 'customer_name', 'receivable_status'])
        ->and($payload['vehicle_stock_number'])->toBe('STK-AEC-PAYLOAD-001')
        ->and($payload['customer_id'])->toBe($customer->id)
        ->and($payload['customer_number'])->toBe('CU-AEC-001')
        ->and($payload['customer_name'])->toBe('安全摘要客戶')
        ->and($payload['receivable_status'])->toBe('paid');

    foreach (['customer_phone', 'id_number', 'birthday', 'address', 'company_id', 'branch_id', 'purchase_cost', 'cogs_amount', 'revenue_amount', 'gross_profit', 'gross_margin', 'profit', 'journal_entry_id', 'accounting_event_id'] as $forbidden) {
        expect(array_key_exists($forbidden, $payload))->toBeFalse();
    }
});

it('completion accounting event uses ReceivableSummaryService semantics', function (): void {
    aeCompletionRegisterVehiclesModule();
    $user = aeCompletionMakeUser('aec-summary@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = aeCompletionMakeVehicle(1, 10, 'STK-AEC-SUMMARY-001', 'vin-aec-summary-001');
    $sale = aeCompletionMakeSale($vehicle, $user, ['sale_price' => 100000, 'paid_amount' => 50000]);
    aeCompletionMakePayment($sale, $user, 100000);
    aeCompletionMakePayment($sale, $user, 50000, 'voided');

    $this->actingAs($user)->patch(aeCompletionCompleteRoute($vehicle, $sale))->assertRedirect();

    $payload = AccountingEvent::query()->firstOrFail()->payload;

    expect($payload['received_amount'])->toBe('100000.00')
        ->and($payload['receivable_status'])->toBe('paid');
});

it('overpaid completion creates event with overpaid receivable status', function (): void {
    aeCompletionRegisterVehiclesModule();
    $user = aeCompletionMakeUser('aec-overpaid@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = aeCompletionMakeVehicle(1, 10, 'STK-AEC-OVERPAID-001', 'vin-aec-overpaid-001');
    $sale = aeCompletionMakeSale($vehicle, $user, ['sale_price' => 100000]);
    aeCompletionMakePayment($sale, $user, 120000);

    $this->actingAs($user)->patch(aeCompletionCompleteRoute($vehicle, $sale))->assertRedirect();

    $event = AccountingEvent::query()->firstOrFail();

    expect($event->payload['receivable_status'])->toBe('overpaid')
        ->and($event->amount)->toBe('100000.00');
});

it('completion failure does not create accounting event', function (): void {
    aeCompletionRegisterVehiclesModule();
    $user = aeCompletionMakeUser('aec-failure@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = aeCompletionMakeVehicle(1, 10, 'STK-AEC-FAIL-001', 'vin-aec-fail-001');
    $sale = aeCompletionMakeSale($vehicle, $user, ['sale_price' => 100000]);
    aeCompletionMakePayment($sale, $user, 50000);

    $this->actingAs($user)
        ->patch(aeCompletionCompleteRoute($vehicle, $sale))
        ->assertStatus(422);

    expect(AccountingEvent::count())->toBe(0);
});

it('unauthorized completion does not create accounting event', function (): void {
    aeCompletionRegisterVehiclesModule();
    $user = aeCompletionMakeUser('aec-unauthorized@example.com');
    $user->givePermissionTo('module.vehicles.view');
    $vehicle = aeCompletionMakeVehicle(1, 10, 'STK-AEC-UNAUTH-001', 'vin-aec-unauth-001');
    $sale = aeCompletionMakeSale($vehicle, $user);
    aeCompletionMakePayment($sale, $user, 100000);

    $this->actingAs($user)
        ->patch(aeCompletionCompleteRoute($vehicle, $sale))
        ->assertForbidden();

    expect(AccountingEvent::count())->toBe(0);
});

it('cross tenant completion remains 404 and does not create accounting event', function (): void {
    aeCompletionRegisterVehiclesModule();
    $user = aeCompletionMakeUser('aec-cross@example.com', 1, 10);
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $owner = aeCompletionMakeUser('aec-cross-owner@example.com', 2, 20);
    $vehicle = aeCompletionMakeVehicle(2, 20, 'STK-AEC-CROSS-001', 'vin-aec-cross-001');
    $sale = aeCompletionMakeSale($vehicle, $owner);
    aeCompletionMakePayment($sale, $owner, 100000);

    $this->actingAs($user)
        ->patch(aeCompletionCompleteRoute($vehicle, $sale))
        ->assertNotFound();

    expect(AccountingEvent::count())->toBe(0);
});

it('repeated service call is idempotent for same sale', function (): void {
    $user = aeCompletionMakeUser('aec-idempotent@example.com');
    $vehicle = aeCompletionMakeVehicle(1, 10, 'STK-AEC-IDEMP-001', 'vin-aec-idemp-001');
    $sale = aeCompletionMakeSale($vehicle, $user, [
        'completed_at' => now(),
        'completed_by' => $user->id,
    ]);
    aeCompletionMakePayment($sale, $user, 100000);

    $service = app(AccountingEventService::class);
    $first = $service->createVehicleSaleCompletedEvent($sale->fresh(), $user);
    $second = $service->createVehicleSaleCompletedEvent($sale->fresh(), $user);

    expect($second->id)->toBe($first->id)
        ->and(AccountingEvent::count())->toBe(1);
});

it('completion does not create journal draft or journal lines', function (): void {
    aeCompletionRegisterVehiclesModule();
    $user = aeCompletionMakeUser('aec-no-journal@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = aeCompletionMakeVehicle(1, 10, 'STK-AEC-NOJOURNAL-001', 'vin-aec-nojournal-001');
    $sale = aeCompletionMakeSale($vehicle, $user);
    aeCompletionMakePayment($sale, $user, 100000);

    $this->actingAs($user)->patch(aeCompletionCompleteRoute($vehicle, $sale))->assertRedirect();

    expect(AccountingEvent::count())->toBe(1)
        ->and(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});

it('accounting event appears in readonly workspace for accounting user', function (): void {
    aeCompletionRegisterVehiclesModule();
    aeCompletionRegisterAccountingEventsModule();
    $user = aeCompletionMakeUser('aec-workflow-completer@example.com');
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $accountingUser = aeCompletionMakeUser('aec-workspace-accounting@example.com');
    $accountingUser->givePermissionTo('module.accounting.events.view');
    $vehicle = aeCompletionMakeVehicle(1, 10, 'STK-AEC-WORKSPACE-001', 'vin-aec-workspace-001');
    $sale = aeCompletionMakeSale($vehicle, $user);
    aeCompletionMakePayment($sale, $user, 100000);

    $this->actingAs($user)->patch(aeCompletionCompleteRoute($vehicle, $sale))->assertRedirect();
    $event = AccountingEvent::query()->firstOrFail();

    $this->actingAs($accountingUser)
        ->get(route('employee-system.accounting.events.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('events.data.0.source_number', 'STK-AEC-WORKSPACE-001'));

    $this->actingAs($accountingUser)
        ->get(route('employee-system.accounting.events.show', $event->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('event.source_number', 'STK-AEC-WORKSPACE-001')
            ->where('event.payload.vehicle_stock_number', 'STK-AEC-WORKSPACE-001')
        );
});
