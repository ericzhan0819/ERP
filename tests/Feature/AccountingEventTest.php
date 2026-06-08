<?php

use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingJournalEntry;
use App\Models\Module;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function ensureAccountingEventTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'Accounting Event Company '.$companyId,
            'code' => 'AE'.$companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            [
                'company_id' => $companyId,
                'name' => 'Accounting Event Branch '.$branchId,
                'code' => 'AEB'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function makeAccountingEventUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    ensureAccountingEventTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'Accounting Event User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function makeAccountingEventJournal(User $actor): AccountingJournalEntry
{
    AccountingAccount::create([
        'company_id' => $actor->company_id,
        'branch_id' => $actor->branch_id,
        'code' => 'AE-CASH-'.uniqid(),
        'name' => 'Accounting Event Cash',
        'type' => 'asset',
        'opening_balance' => 0,
        'is_active' => true,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    return AccountingJournalEntry::create([
        'company_id' => $actor->company_id,
        'branch_id' => $actor->branch_id,
        'journal_number' => 'JE-AE-'.uniqid(),
        'entry_date' => '2026-06-08',
        'summary' => 'Accounting event converted draft placeholder',
        'status' => 'draft',
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);
}

function makeAccountingEventRecord(User $creator, array $overrides = []): AccountingEvent
{
    return AccountingEvent::create(array_merge([
        'company_id' => $creator->company_id,
        'branch_id' => $creator->branch_id,
        'source_type' => 'vehicle_sale_completion',
        'source_id' => 1001,
        'source_number' => 'SALE-FOUNDATION-001',
        'event_type' => 'vehicle_sale_completed',
        'event_date' => '2026-06-08',
        'status' => 'pending',
        'currency' => 'TWD',
        'amount' => 100000,
        // 技術註解：payload 僅示範後端控制的非敏感摘要，不放客戶敏感個資、tenant raw ids、毛利或利潤資料。
        'payload' => [
            'source_number' => 'SALE-FOUNDATION-001',
            'vehicle_stock_number' => 'STK-AE-001',
            'receivable_status' => 'paid',
        ],
        'review_note' => 'Foundation review note',
        'created_by' => $creator->id,
    ], $overrides));
}

function registerAccountingEventVehiclesModule(): void
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

function makeAccountingEventVehicle(int $companyId, int $branchId, string $stock, string $vin): Vehicle
{
    ensureAccountingEventTenantRows($companyId, $branchId);

    return Vehicle::create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'stock_number' => $stock,
        'vin' => $vin,
        'brand' => 'Toyota',
        'model' => 'Camry',
        'model_year' => 2023,
        'lifecycle_status' => 'sold',
    ]);
}

function makeAccountingEventCompletedCandidateSale(Vehicle $vehicle, User $actor): VehicleSale
{
    $sale = VehicleSale::create([
        'company_id' => $vehicle->company_id,
        'branch_id' => $vehicle->branch_id,
        'vehicle_id' => $vehicle->id,
        'customer_name' => 'Accounting Event Customer',
        'customer_phone' => '0900000000',
        'sale_price' => 100000,
        'deposit_amount' => 0,
        'paid_amount' => 0,
        'sale_status' => 'sold',
        'sold_at' => '2026-06-08',
        'salesperson_name' => 'Sales',
        'commission_amount' => 0,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    VehicleSalePayment::create([
        'company_id' => $sale->company_id,
        'branch_id' => $sale->branch_id,
        'vehicle_id' => $sale->vehicle_id,
        'vehicle_sale_id' => $sale->id,
        'customer_id' => null,
        'payment_number' => 'PAY-AE-'.uniqid(),
        'payment_type' => 'final_payment',
        'payment_method' => 'cash',
        'amount' => 100000,
        'paid_at' => '2026-06-08',
        'status' => 'received',
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    return $sale;
}

it('accounting_events table 存在並具備 foundation 欄位', function (): void {
    expect(Schema::hasTable('accounting_events'))->toBeTrue()
        ->and(Schema::hasColumns('accounting_events', [
            'id',
            'company_id',
            'branch_id',
            'source_type',
            'source_id',
            'source_number',
            'event_type',
            'event_date',
            'status',
            'currency',
            'amount',
            'payload',
            'review_note',
            'created_by',
            'reviewed_by',
            'converted_journal_entry_id',
            'voided_by',
            'voided_at',
            'void_reason',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

it('AccountingEvent model 可建立讀取欄位 casts 與 relationships', function (): void {
    $creator = makeAccountingEventUser('accounting-event-creator@example.com');
    $reviewer = makeAccountingEventUser('accounting-event-reviewer@example.com');
    $voider = makeAccountingEventUser('accounting-event-voider@example.com');
    $journal = makeAccountingEventJournal($creator);

    $voidedAt = now()->setMicrosecond(0);
    $event = makeAccountingEventRecord($creator, [
        'status' => 'voided',
        'reviewed_by' => $reviewer->id,
        'converted_journal_entry_id' => $journal->id,
        'voided_by' => $voider->id,
        'voided_at' => $voidedAt,
        'void_reason' => 'Foundation void reason',
    ])->fresh();

    expect($event->company_id)->toBe($creator->company_id)
        ->and($event->branch_id)->toBe($creator->branch_id)
        ->and($event->source_id)->toBe(1001)
        ->and($event->payload)->toBeArray()
        ->and($event->payload['vehicle_stock_number'])->toBe('STK-AE-001')
        ->and($event->amount)->toBe('100000.00')
        ->and($event->event_date?->toDateString())->toBe('2026-06-08')
        ->and($event->voided_at?->toDateTimeString())->toBe($voidedAt->toDateTimeString())
        ->and($event->creator?->id)->toBe($creator->id)
        ->and($event->reviewer?->id)->toBe($reviewer->id)
        ->and($event->voider?->id)->toBe($voider->id)
        ->and($event->convertedJournalEntry?->id)->toBe($journal->id)
        ->and($event->company?->id)->toBe($creator->company_id)
        ->and($event->branch?->id)->toBe($creator->branch_id);
});

it('accounting events config 包含 foundation source event 與 statuses', function (): void {
    expect(config('accounting_events.source_types.vehicle_sale_completion'))->toBe('車輛交易完成')
        ->and(config('accounting_events.event_types.vehicle_sale_completed'))->toBe('車輛交易完成')
        ->and(array_keys(config('accounting_events.statuses')))->toContain('pending', 'reviewed', 'converted', 'voided');
});

it('AccountingEvent 可用 company 與 branch 條件安全 scoped 查詢', function (): void {
    $user = makeAccountingEventUser('accounting-event-scope@example.com', 1, 10);
    $sameBranch = makeAccountingEventRecord($user, ['source_number' => 'AE-SAME-BRANCH']);
    $sameCompanyOtherBranchUser = makeAccountingEventUser('accounting-event-other-branch@example.com', 1, 11);
    $sameCompanyOtherBranch = makeAccountingEventRecord($sameCompanyOtherBranchUser, ['source_number' => 'AE-OTHER-BRANCH']);
    $otherCompanyUser = makeAccountingEventUser('accounting-event-other-company@example.com', 2, 20);
    makeAccountingEventRecord($otherCompanyUser, ['source_number' => 'AE-OTHER-COMPANY']);

    $companyScoped = AccountingEvent::query()
        ->where('company_id', $user->company_id)
        ->pluck('id')
        ->all();

    $branchScoped = AccountingEvent::query()
        ->where('company_id', $user->company_id)
        ->where('branch_id', $user->branch_id)
        ->pluck('id')
        ->all();

    expect($companyScoped)->toContain($sameBranch->id, $sameCompanyOtherBranch->id)
        ->and($companyScoped)->not->toContain(AccountingEvent::query()->where('source_number', 'AE-OTHER-COMPANY')->value('id'))
        ->and($branchScoped)->toBe([$sameBranch->id]);
});

it('completion route 成功完成交易後會建立 pending Accounting Event', function (): void {
    registerAccountingEventVehiclesModule();
    $user = makeAccountingEventUser('accounting-event-completion-regression@example.com', 1, 10);
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = makeAccountingEventVehicle(1, 10, 'STK-AE-COMP-001', 'vin-ae-comp-001');
    $sale = makeAccountingEventCompletedCandidateSale($vehicle, $user);

    expect(AccountingEvent::count())->toBe(0);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]), [
            'completion_note' => 'Completion creates pending accounting event in phase 3.',
        ])
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $sale->refresh();

    expect($sale->completed_at)->not->toBeNull()
        ->and($sale->completed_by)->toBe($user->id)
        ->and(AccountingEvent::count())->toBe(1)
        ->and(AccountingEvent::query()->first()?->status)->toBe('pending');
});
