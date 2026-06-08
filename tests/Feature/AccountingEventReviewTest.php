<?php

use App\Models\AccountingEvent;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalEntryLine;
use App\Models\ActivityLog;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function aeReviewEnsureTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'AE Review Company '.$companyId,
            'code' => 'AER'.$companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            [
                'company_id' => $companyId,
                'name' => 'AE Review Branch '.$branchId,
                'code' => 'AERB'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function aeReviewRegisterAccountingEventsModule(): void
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

function aeReviewMakeUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    aeReviewEnsureTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'AE Review User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function aeReviewMakeEvent(User $creator, array $overrides = []): AccountingEvent
{
    return AccountingEvent::create(array_merge([
        'company_id' => $creator->company_id,
        'branch_id' => $creator->branch_id,
        'source_type' => 'vehicle_sale_completion',
        'source_id' => 1001,
        'source_number' => 'SALE-AER-001',
        'event_type' => 'vehicle_sale_completed',
        'event_date' => '2026-06-08',
        'status' => 'pending',
        'currency' => 'TWD',
        'amount' => 100000,
        // 技術註解：測試資料保留安全摘要，敏感鍵只用於 deny-list 與 audit 檢查，不作為會計真實來源。
        'payload' => [
            'vehicle_stock_number' => 'STK-AER-001',
            'receivable_status' => 'paid',
        ],
        'created_by' => $creator->id,
    ], $overrides));
}

function aeReviewRoute(AccountingEvent $event): string
{
    return route('employee-system.accounting.events.review', $event->id);
}

it('pending accounting event can be reviewed by authorized accounting user', function (): void {
    aeReviewRegisterAccountingEventsModule();
    $user = aeReviewMakeUser('aer-success@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.review']);
    $event = aeReviewMakeEvent($user);

    $this->actingAs($user)
        ->patch(aeReviewRoute($event), ['review_note' => 'Reviewed and ready for future draft generation.'])
        ->assertRedirect(route('employee-system.accounting.events.show', $event->id));

    $event->refresh();

    expect($event->status)->toBe('reviewed')
        ->and($event->review_note)->toBe('Reviewed and ready for future draft generation.')
        ->and($event->reviewed_by)->toBe($user->id)
        ->and($event->reviewed_at)->not->toBeNull()
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and($event->voided_at)->toBeNull()
        ->and(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});

it('view-only user cannot review accounting event', function (): void {
    aeReviewRegisterAccountingEventsModule();
    $user = aeReviewMakeUser('aer-view-only@example.com');
    $user->givePermissionTo('module.accounting.events.view');
    $event = aeReviewMakeEvent($user);

    $this->actingAs($user)
        ->patch(aeReviewRoute($event), ['review_note' => 'Should fail'])
        ->assertForbidden();

    $event->refresh();

    expect($event->status)->toBe('pending')
        ->and($event->reviewed_by)->toBeNull()
        ->and($event->reviewed_at)->toBeNull();
});

it('module.accounting.view alone cannot review accounting event', function (): void {
    aeReviewRegisterAccountingEventsModule();
    Permission::findOrCreate('module.accounting.view', 'web');
    $user = aeReviewMakeUser('aer-accounting-view-only@example.com');
    $user->givePermissionTo('module.accounting.view');
    $event = aeReviewMakeEvent($user);

    $this->actingAs($user)
        ->patch(aeReviewRoute($event), ['review_note' => 'Should fail'])
        ->assertForbidden();

    $event->refresh();

    expect($event->status)->toBe('pending')
        ->and($event->reviewed_by)->toBeNull()
        ->and($event->reviewed_at)->toBeNull();
});

it('cross tenant review returns 404 and does not update event', function (): void {
    aeReviewRegisterAccountingEventsModule();
    $user = aeReviewMakeUser('aer-cross-user@example.com', 1, 10);
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.review']);
    $owner = aeReviewMakeUser('aer-cross-owner@example.com', 2, 20);
    $event = aeReviewMakeEvent($owner);

    $this->actingAs($user)
        ->patch(aeReviewRoute($event), ['review_note' => 'Cross tenant attempt'])
        ->assertNotFound();

    $event->refresh();

    expect($event->status)->toBe('pending')
        ->and($event->reviewed_by)->toBeNull()
        ->and($event->reviewed_at)->toBeNull();
});

it('only pending event can be reviewed', function (): void {
    aeReviewRegisterAccountingEventsModule();
    $user = aeReviewMakeUser('aer-status-user@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.review']);
    $reviewedAt = now()->subDay()->setMicrosecond(0);
    $events = [
        aeReviewMakeEvent($user, ['status' => 'reviewed', 'reviewed_at' => $reviewedAt, 'reviewed_by' => $user->id]),
        aeReviewMakeEvent($user, ['status' => 'converted', 'converted_journal_entry_id' => null, 'reviewed_at' => $reviewedAt, 'reviewed_by' => $user->id]),
        aeReviewMakeEvent($user, ['status' => 'voided', 'voided_at' => now(), 'reviewed_at' => $reviewedAt, 'reviewed_by' => $user->id]),
    ];

    foreach ($events as $event) {
        $originalStatus = $event->status;

        $this->actingAs($user)
            ->patch(aeReviewRoute($event), ['review_note' => 'Should not overwrite'])
            ->assertForbidden();

        $event->refresh();

        expect($event->status)->toBe($originalStatus)
            ->and($event->reviewed_at?->toDateTimeString())->toBe($reviewedAt->toDateTimeString());
    }
});

it('review request rejects system and accounting payload fields', function (): void {
    aeReviewRegisterAccountingEventsModule();
    $user = aeReviewMakeUser('aer-deny-list@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.review']);
    $event = aeReviewMakeEvent($user, ['amount' => 100000]);

    $this->actingAs($user)
        ->patch(aeReviewRoute($event), [
            'review_note' => 'Attempt with forbidden fields',
            'status' => 'reviewed',
            'amount' => 1,
            'payload' => ['profit' => 999],
            'company_id' => 999,
            'branch_id' => 999,
            'reviewed_by' => 999,
            'reviewed_at' => now()->toDateTimeString(),
            'converted_journal_entry_id' => 999,
            'revenue_amount' => 1,
            'cogs_amount' => 1,
            'gross_profit' => 1,
            'gross_margin' => 1,
            'profit' => 1,
            'journal_entry_id' => 999,
            'customer_phone' => '0900000000',
            'id_number' => 'A123456789',
            'birthday' => '1990-01-01',
            'address' => 'Hidden',
        ])
        ->assertForbidden();

    $event->refresh();

    expect($event->status)->toBe('pending')
        ->and($event->amount)->toBe('100000.00')
        ->and($event->reviewed_by)->toBeNull()
        ->and($event->reviewed_at)->toBeNull()
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and($event->payload)->toBe(['vehicle_stock_number' => 'STK-AER-001', 'receivable_status' => 'paid']);
});

it('review writes audit log without sensitive payload', function (): void {
    aeReviewRegisterAccountingEventsModule();
    $user = aeReviewMakeUser('aer-audit@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.review']);
    $event = aeReviewMakeEvent($user, [
        'payload' => [
            'vehicle_stock_number' => 'STK-AUDIT-SAFE',
            'customer_phone' => '0900000000',
            'id_number' => 'A123456789',
            'birthday' => '1990-01-01',
            'address' => 'Hidden',
            'profit' => 1,
            'gross_profit' => 1,
            'gross_margin' => 1,
            'cogs_amount' => 1,
            'revenue_amount' => 1,
            'company_id' => 1,
            'branch_id' => 10,
        ],
    ]);

    $this->actingAs($user)
        ->patch(aeReviewRoute($event), ['review_note' => 'Audit safe review'])
        ->assertRedirect();

    $log = ActivityLog::query()->latest('id')->firstOrFail();
    $json = json_encode([
        'metadata' => $log->metadata,
        'old_values' => $log->old_values,
        'new_values' => $log->new_values,
    ], JSON_THROW_ON_ERROR);

    expect($log->action)->toBe('accounting_event.reviewed')
        ->and($log->event)->toBe('accounting_event.reviewed')
        ->and($log->description)->toBe('Accounting event reviewed')
        ->and($log->old_values['old_status'] ?? null)->toBe('pending')
        ->and($log->new_values['new_status'] ?? null)->toBe('reviewed')
        ->and($log->new_values['review_note'] ?? null)->toBe('Audit safe review')
        ->and($log->new_values['reviewed_at'] ?? null)->not->toBeNull();

    foreach (['customer_phone', 'id_number', 'birthday', 'address', 'profit', 'gross_profit', 'gross_margin', 'cogs_amount', 'revenue_amount', 'company_id', 'branch_id'] as $forbidden) {
        expect($json)->not->toContain($forbidden);
    }
});

it('show page exposes can.review only when authorized and pending', function (): void {
    aeReviewRegisterAccountingEventsModule();
    $reviewUser = aeReviewMakeUser('aer-show-review@example.com');
    $reviewUser->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.review']);
    $viewUser = aeReviewMakeUser('aer-show-view@example.com');
    $viewUser->givePermissionTo('module.accounting.events.view');
    $pending = aeReviewMakeEvent($reviewUser, ['source_number' => 'AER-CAN-PENDING']);
    $reviewed = aeReviewMakeEvent($reviewUser, ['source_number' => 'AER-CAN-REVIEWED', 'status' => 'reviewed']);

    $this->actingAs($reviewUser)
        ->get(route('employee-system.accounting.events.show', $pending->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.review', true));

    $this->actingAs($viewUser)
        ->get(route('employee-system.accounting.events.show', $pending->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.review', false));

    $this->actingAs($reviewUser)
        ->get(route('employee-system.accounting.events.show', $reviewed->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.review', false));
});

it('RolePermissionSeeder registers accounting events review permission', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = Role::findByName('admin', 'web');
    $accounting = Role::findByName('accounting', 'web');
    $viewer = Role::findByName('viewer', 'web');

    expect(Permission::query()->where('name', 'module.accounting.events.review')->exists())->toBeTrue()
        ->and($admin->hasPermissionTo('module.accounting.events.review'))->toBeTrue()
        ->and($accounting->hasPermissionTo('module.accounting.events.review'))->toBeTrue()
        ->and($viewer->hasPermissionTo('module.accounting.events.review'))->toBeFalse()
        ->and(Permission::query()->where('name', 'module.accounting.events.convert')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'module.accounting.events.void')->exists())->toBeFalse();
});

it('Staff permission matrix displays accounting events review action', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('employee-system.staff-permissions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('permissionMatrix', function ($matrix): bool {
                $matrix = is_array($matrix) ? $matrix : $matrix->all();

                return isset($matrix['accounting.events'])
                    && ($matrix['accounting.events']['label'] ?? null) === '會計事件'
                    && ($matrix['accounting.events']['actions']['view']['permission'] ?? null) === 'module.accounting.events.view'
                    && ($matrix['accounting.events']['actions']['review']['permission'] ?? null) === 'module.accounting.events.review';
            })
            ->where('actionLabels.review', '覆核')
        );
});

it('review route does not create journal draft or journal lines', function (): void {
    aeReviewRegisterAccountingEventsModule();
    $user = aeReviewMakeUser('aer-no-journal@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.review']);
    $event = aeReviewMakeEvent($user);

    $this->actingAs($user)
        ->patch(aeReviewRoute($event), ['review_note' => 'No journal draft'])
        ->assertRedirect();

    expect(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});
