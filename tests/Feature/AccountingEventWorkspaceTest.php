<?php

use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingJournalEntry;
use App\Models\Module;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function ensureAccountingEventWorkspaceTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'Accounting Event Workspace Company '.$companyId,
            'code' => 'AEW'.$companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            [
                'company_id' => $companyId,
                'name' => 'Accounting Event Workspace Branch '.$branchId,
                'code' => 'AEWB'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function makeAccountingEventWorkspaceUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    ensureAccountingEventWorkspaceTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'Accounting Event Workspace User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function registerAccountingEventWorkspaceModule(): void
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
    Permission::findOrCreate('module.accounting.events.review', 'web');
}

function registerAccountingEventWorkspaceVehiclesModule(): void
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
            'icon' => 'car',
            'sort_order' => 30,
            'is_enabled' => true,
            'is_active' => true,
            'active_patterns' => ['employee-system.vehicles.*'],
        ]
    );

    Permission::findOrCreate('module.vehicles.view', 'web');
    Permission::findOrCreate('module.vehicles.sales.completion.confirm', 'web');
}

function makeAccountingEventWorkspaceRecord(User $creator, array $overrides = []): AccountingEvent
{
    return AccountingEvent::create(array_merge([
        'company_id' => $creator->company_id,
        'branch_id' => $creator->branch_id,
        'source_type' => 'vehicle_sale_completion',
        'source_id' => 1001,
        'source_number' => 'SALE-AEW-001',
        'event_type' => 'vehicle_sale_completed',
        'event_date' => '2026-06-08',
        'status' => 'pending',
        'currency' => 'TWD',
        'amount' => 100000,
        // 技術註解：測試 payload 只保留非敏感候選摘要，敏感與毛利鍵另於 sanitizer 測試驗證會被後端移除。
        'payload' => [
            'vehicle_stock_number' => 'STK-AEW-001',
            'receivable_status' => 'paid',
        ],
        'review_note' => 'Readonly review note',
        'created_by' => $creator->id,
    ], $overrides));
}

function makeAccountingEventWorkspaceJournal(User $actor): AccountingJournalEntry
{
    AccountingAccount::create([
        'company_id' => $actor->company_id,
        'branch_id' => $actor->branch_id,
        'code' => 'AEW-CASH-'.uniqid(),
        'name' => 'Accounting Event Workspace Cash',
        'type' => 'asset',
        'opening_balance' => 0,
        'is_active' => true,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    return AccountingJournalEntry::create([
        'company_id' => $actor->company_id,
        'branch_id' => $actor->branch_id,
        'journal_number' => 'JE-AEW-'.uniqid(),
        'entry_date' => '2026-06-08',
        'summary' => 'Accounting event converted draft placeholder',
        'status' => 'draft',
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);
}

function makeAccountingEventWorkspaceVehicle(int $companyId, int $branchId, string $stock, string $vin): Vehicle
{
    ensureAccountingEventWorkspaceTenantRows($companyId, $branchId);

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

function makeAccountingEventWorkspaceCompletedCandidateSale(Vehicle $vehicle, User $actor): VehicleSale
{
    $sale = VehicleSale::create([
        'company_id' => $vehicle->company_id,
        'branch_id' => $vehicle->branch_id,
        'vehicle_id' => $vehicle->id,
        'customer_name' => 'Accounting Event Workspace Customer',
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
        'payment_number' => 'PAY-AEW-'.uniqid(),
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

it('沒有 accounting events view 權限不可進入 index', function (): void {
    registerAccountingEventWorkspaceModule();
    $user = makeAccountingEventWorkspaceUser('aew-denied@example.com');

    $this->actingAs($user)
        ->get(route('employee-system.accounting.events.index'))
        ->assertForbidden();
});

it('有 accounting events view 權限可進入 index 並取得 labels', function (): void {
    registerAccountingEventWorkspaceModule();
    $user = makeAccountingEventWorkspaceUser('aew-allowed@example.com');
    $user->givePermissionTo('module.accounting.events.view');
    makeAccountingEventWorkspaceRecord($user, ['source_number' => 'SALE-AEW-LABEL']);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.events.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Events/Index')
            ->where('events.data.0.source_number', 'SALE-AEW-LABEL')
            ->where('events.data.0.source_type_label', config('accounting_events.source_types.vehicle_sale_completion'))
            ->where('events.data.0.event_type_label', config('accounting_events.event_types.vehicle_sale_completed'))
            ->where('events.data.0.status_label', config('accounting_events.statuses.pending'))
            ->missing('events.data.0.payload')
        );
});

it('index 只回傳 tenant scoped accounting events', function (): void {
    registerAccountingEventWorkspaceModule();
    $user = makeAccountingEventWorkspaceUser('aew-scope@example.com', 1, 10);
    $user->givePermissionTo('module.accounting.events.view');
    makeAccountingEventWorkspaceRecord($user, ['source_number' => 'AEW-SAME-BRANCH']);
    makeAccountingEventWorkspaceRecord($user, ['branch_id' => null, 'source_number' => 'AEW-BRANCH-NULL']);
    $otherBranchUser = makeAccountingEventWorkspaceUser('aew-other-branch@example.com', 1, 11);
    makeAccountingEventWorkspaceRecord($otherBranchUser, ['source_number' => 'AEW-OTHER-BRANCH']);
    $otherCompanyUser = makeAccountingEventWorkspaceUser('aew-other-company@example.com', 2, 20);
    makeAccountingEventWorkspaceRecord($otherCompanyUser, ['source_number' => 'AEW-OTHER-COMPANY']);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.events.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('events.data', function ($rows): bool {
            $numbers = collect($rows)->pluck('source_number')->all();

            return in_array('AEW-SAME-BRANCH', $numbers, true)
                && in_array('AEW-BRANCH-NULL', $numbers, true)
                && ! in_array('AEW-OTHER-BRANCH', $numbers, true)
                && ! in_array('AEW-OTHER-COMPANY', $numbers, true);
        }));

    $companyUser = makeAccountingEventWorkspaceUser('aew-company-scope@example.com', 1, null);
    $companyUser->givePermissionTo('module.accounting.events.view');

    $this->actingAs($companyUser)
        ->get(route('employee-system.accounting.events.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('events.data', function ($rows): bool {
            $numbers = collect($rows)->pluck('source_number')->all();

            return in_array('AEW-SAME-BRANCH', $numbers, true)
                && in_array('AEW-BRANCH-NULL', $numbers, true)
                && in_array('AEW-OTHER-BRANCH', $numbers, true)
                && ! in_array('AEW-OTHER-COMPANY', $numbers, true);
        }));
});

it('index filters 可依 q source_type event_type status date range 篩選', function (): void {
    registerAccountingEventWorkspaceModule();
    $user = makeAccountingEventWorkspaceUser('aew-filter@example.com');
    $user->givePermissionTo('module.accounting.events.view');
    makeAccountingEventWorkspaceRecord($user, ['source_number' => 'AEW-FILTER-TARGET', 'source_type' => 'vehicle_sale_payment', 'event_type' => 'payment_received', 'status' => 'reviewed', 'event_date' => '2026-06-07']);
    makeAccountingEventWorkspaceRecord($user, ['source_number' => 'AEW-FILTER-OTHER', 'source_type' => 'vehicle_cost', 'event_type' => 'vehicle_cost_recorded', 'status' => 'pending', 'event_date' => '2026-05-01']);

    $queries = [
        ['q' => 'TARGET'],
        ['source_type' => 'vehicle_sale_payment'],
        ['event_type' => 'payment_received'],
        ['status' => 'reviewed'],
        ['date_from' => '2026-06-01', 'date_to' => '2026-06-30'],
    ];

    foreach ($queries as $query) {
        $this->actingAs($user)
            ->get(route('employee-system.accounting.events.index', $query))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.data.0.source_number', 'AEW-FILTER-TARGET')
                ->missing('events.data.1')
            );
    }
});

it('有權限可看 show 與 converted journal allowlist', function (): void {
    registerAccountingEventWorkspaceModule();
    $user = makeAccountingEventWorkspaceUser('aew-show@example.com');
    $user->givePermissionTo('module.accounting.events.view');
    $journal = makeAccountingEventWorkspaceJournal($user);
    $event = makeAccountingEventWorkspaceRecord($user, [
        'source_number' => 'AEW-SHOW-001',
        'status' => 'converted',
        'converted_journal_entry_id' => $journal->id,
    ]);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.events.show', $event->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Events/Show')
            ->where('event.source_number', 'AEW-SHOW-001')
            ->where('event.status_label', config('accounting_events.statuses.converted'))
            ->where('event.amount', '100000.00')
            ->where('event.converted_journal_entry.id', $journal->id)
            ->where('event.converted_journal_entry.journal_number', $journal->journal_number)
            ->where('can.review', false)
            ->has('event.reviewed_at')
            ->missing('event.company_id')
            ->missing('event.branch_id')
        );
});

it('show 跨 tenant 優先 404', function (): void {
    registerAccountingEventWorkspaceModule();
    $user = makeAccountingEventWorkspaceUser('aew-show-404@example.com', 1, 10);
    $user->givePermissionTo('module.accounting.events.view');
    $otherCompanyUser = makeAccountingEventWorkspaceUser('aew-show-other-company@example.com', 2, 20);
    $event = makeAccountingEventWorkspaceRecord($otherCompanyUser, ['source_number' => 'AEW-CROSS-TENANT']);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.events.show', $event->id))
        ->assertNotFound();
});

it('show payload 會排除敏感與毛利欄位', function (): void {
    registerAccountingEventWorkspaceModule();
    $user = makeAccountingEventWorkspaceUser('aew-sanitizer@example.com');
    $user->givePermissionTo('module.accounting.events.view');
    $event = makeAccountingEventWorkspaceRecord($user, [
        'payload' => [
            'vehicle_stock_number' => 'STK-SAFE-001',
            'id_number' => 'A123456789',
            'birthday' => '1990-01-01',
            'address' => 'Hidden address',
            'gross_profit' => 999,
            'gross_margin' => 0.2,
            'company_id' => 1,
            'branch_id' => 10,
            'nested' => [
                'vehicle_stock_number' => 'STK-NESTED-SAFE',
                'profit' => 100,
                'revenue_amount' => 100000,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.events.show', $event->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('event.payload', function ($payload): bool {
            $payload = is_array($payload) ? $payload : $payload->all();

            return ($payload['vehicle_stock_number'] ?? null) === 'STK-SAFE-001'
                && ($payload['nested']['vehicle_stock_number'] ?? null) === 'STK-NESTED-SAFE'
                && ! isset($payload['id_number'], $payload['birthday'], $payload['address'], $payload['gross_profit'], $payload['gross_margin'], $payload['company_id'], $payload['branch_id'], $payload['nested']['profit'], $payload['nested']['revenue_amount']);
        }));
});

it('RolePermissionSeeder 註冊 accounting-events module 與 view permission', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $module = Module::query()->where('key', 'accounting-events')->firstOrFail();
    $admin = Role::findByName('admin', 'web');
    $accounting = Role::findByName('accounting', 'web');
    $viewer = Role::findByName('viewer', 'web');

    expect($module->base_permission)->toBe('module.accounting.events.view')
        ->and($module->route_name)->toBe('employee-system.accounting.events.index')
        ->and(Permission::query()->where('name', 'module.accounting.events.view')->exists())->toBeTrue()
        ->and($admin->hasPermissionTo('module.accounting.events.view'))->toBeTrue()
        ->and($accounting->hasPermissionTo('module.accounting.events.view'))->toBeTrue()
        ->and($viewer->hasPermissionTo('module.accounting.events.view'))->toBeFalse()
        ->and(Permission::query()->where('name', 'module.accounting.events.review')->exists())->toBeTrue()
        ->and($admin->hasPermissionTo('module.accounting.events.review'))->toBeTrue()
        ->and($accounting->hasPermissionTo('module.accounting.events.review'))->toBeTrue()
        ->and($viewer->hasPermissionTo('module.accounting.events.review'))->toBeFalse()
        ->and(Permission::query()->whereIn('name', [
            'module.accounting.events.create',
            'module.accounting.events.convert',
            'module.accounting.events.void',
        ])->exists())->toBeFalse();
});

it('legacy module.accounting.view 不足以進入 accounting events', function (): void {
    registerAccountingEventWorkspaceModule();
    Permission::findOrCreate('module.accounting.view', 'web');
    $user = makeAccountingEventWorkspaceUser('aew-legacy-only@example.com');
    $user->givePermissionTo('module.accounting.view');

    $this->actingAs($user)
        ->get(route('employee-system.accounting.events.index'))
        ->assertForbidden();
});

it('Staff Permission matrix 顯示 accounting.events', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('permissionMatrix', function ($matrix): bool {
            $matrix = is_array($matrix) ? $matrix : $matrix->all();

            return isset($matrix['accounting.events'])
                && ($matrix['accounting.events']['label'] ?? null) === '會計事件'
                && ($matrix['accounting.events']['actions']['view']['permission'] ?? null) === 'module.accounting.events.view'
                && ($matrix['accounting.events']['actions']['review']['permission'] ?? null) === 'module.accounting.events.review';
        }));
});

it('只提供 accounting event review mutation route', function (): void {
    expect(Route::has('employee-system.accounting.events.create'))->toBeFalse()
        ->and(Route::has('employee-system.accounting.events.store'))->toBeFalse()
        ->and(Route::has('employee-system.accounting.events.review'))->toBeTrue()
        ->and(Route::has('employee-system.accounting.events.convert'))->toBeFalse()
        ->and(Route::has('employee-system.accounting.events.void'))->toBeFalse();
});

it('completion route 成功完成交易後會建立 readonly workspace 可讀的 Accounting Event', function (): void {
    registerAccountingEventWorkspaceVehiclesModule();
    $user = makeAccountingEventWorkspaceUser('aew-completion-regression@example.com', 1, 10);
    $user->givePermissionTo(['module.vehicles.view', 'module.vehicles.sales.completion.confirm']);
    $vehicle = makeAccountingEventWorkspaceVehicle(1, 10, 'STK-AEW-COMP-001', 'vin-aew-comp-001');
    $sale = makeAccountingEventWorkspaceCompletedCandidateSale($vehicle, $user);

    expect(AccountingEvent::count())->toBe(0);

    $this->actingAs($user)
        ->patch(route('employee-system.vehicles.sales.complete', [$vehicle->id, $sale->id]), [
            'completion_note' => 'Readonly workspace can consume completion event.',
        ])
        ->assertRedirect(route('employee-system.vehicles.show', $vehicle->id));

    $sale->refresh();

    expect($sale->completed_at)->not->toBeNull()
        ->and($sale->completed_by)->toBe($user->id)
        ->and(AccountingEvent::count())->toBe(1)
        ->and(AccountingEvent::query()->first()?->source_number)->toBe('STK-AEW-COMP-001');
});
