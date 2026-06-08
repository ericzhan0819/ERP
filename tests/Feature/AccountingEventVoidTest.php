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

function aeVoidEnsureTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'AE Void Company '.$companyId,
            'code' => 'AEV'.$companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            [
                'company_id' => $companyId,
                'name' => 'AE Void Branch '.$branchId,
                'code' => 'AEVB'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function aeVoidRegisterAccountingEventsModule(): void
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
    Permission::findOrCreate('module.accounting.events.void', 'web');
}

function aeVoidMakeUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    aeVoidEnsureTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'AE Void User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function aeVoidMakeEvent(User $creator, array $overrides = []): AccountingEvent
{
    return AccountingEvent::create(array_merge([
        'company_id' => $creator->company_id,
        'branch_id' => $creator->branch_id,
        'source_type' => 'vehicle_sale_completion',
        'source_id' => 2001,
        'source_number' => 'SALE-AEV-001',
        'event_type' => 'vehicle_sale_completed',
        'event_date' => '2026-06-08',
        'status' => 'pending',
        'currency' => 'TWD',
        'amount' => 100000,
        // 技術註解：測試 payload 僅作候選摘要；敏感與毛利鍵用於驗證 audit allowlist 不會輸出完整 payload。
        'payload' => [
            'vehicle_stock_number' => 'STK-AEV-001',
            'receivable_status' => 'paid',
        ],
        'created_by' => $creator->id,
    ], $overrides));
}

function aeVoidRoute(AccountingEvent $event): string
{
    return route('employee-system.accounting.events.void', $event->id);
}

it('pending accounting event can be voided by authorized accounting user', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-pending@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.void']);
    $event = aeVoidMakeEvent($user);

    $this->actingAs($user)
        ->patch(aeVoidRoute($event), ['void_reason' => 'Duplicate completion candidate.'])
        ->assertRedirect(route('employee-system.accounting.events.show', $event->id));

    $event->refresh();

    expect($event->status)->toBe('voided')
        ->and($event->void_reason)->toBe('Duplicate completion candidate.')
        ->and($event->voided_by)->toBe($user->id)
        ->and($event->voided_at)->not->toBeNull()
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});

it('reviewed accounting event can be voided by authorized accounting user', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-reviewed@example.com');
    $reviewer = aeVoidMakeUser('aev-reviewer@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.void']);
    $reviewedAt = now()->subHour()->setMicrosecond(0);
    $event = aeVoidMakeEvent($user, [
        'status' => 'reviewed',
        'review_note' => 'Reviewed before void.',
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => $reviewedAt,
    ]);

    $this->actingAs($user)
        ->patch(aeVoidRoute($event), ['void_reason' => 'Source document was corrected.'])
        ->assertRedirect();

    $event->refresh();

    expect($event->status)->toBe('voided')
        ->and($event->review_note)->toBe('Reviewed before void.')
        ->and($event->reviewed_by)->toBe($reviewer->id)
        ->and($event->reviewed_at?->toDateTimeString())->toBe($reviewedAt->toDateTimeString())
        ->and($event->voided_by)->toBe($user->id)
        ->and($event->voided_at)->not->toBeNull()
        ->and($event->void_reason)->toBe('Source document was corrected.');
});

it('view-only user cannot void accounting event', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-view-only@example.com');
    $user->givePermissionTo('module.accounting.events.view');
    $event = aeVoidMakeEvent($user);

    $this->actingAs($user)
        ->patch(aeVoidRoute($event), ['void_reason' => 'Should fail'])
        ->assertForbidden();

    expect($event->fresh()->status)->toBe('pending');
});

it('review-only user cannot void accounting event', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-review-only@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.review']);
    $event = aeVoidMakeEvent($user);

    $this->actingAs($user)
        ->patch(aeVoidRoute($event), ['void_reason' => 'Should fail'])
        ->assertForbidden();

    expect($event->fresh()->status)->toBe('pending');
});

it('module.accounting.view alone cannot void accounting event', function (): void {
    aeVoidRegisterAccountingEventsModule();
    Permission::findOrCreate('module.accounting.view', 'web');
    $user = aeVoidMakeUser('aev-accounting-view@example.com');
    $user->givePermissionTo('module.accounting.view');
    $event = aeVoidMakeEvent($user);

    $this->actingAs($user)
        ->patch(aeVoidRoute($event), ['void_reason' => 'Should fail'])
        ->assertForbidden();

    expect($event->fresh()->status)->toBe('pending');
});

it('cross tenant void returns 404 and does not update event', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-cross-user@example.com', 1, 10);
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.void']);
    $owner = aeVoidMakeUser('aev-cross-owner@example.com', 2, 20);
    $event = aeVoidMakeEvent($owner);

    $this->actingAs($user)
        ->patch(aeVoidRoute($event), ['void_reason' => 'Cross tenant attempt'])
        ->assertNotFound();

    expect($event->fresh()->status)->toBe('pending');
});

it('converted accounting event cannot be voided', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-converted@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.void']);
    $journal = AccountingJournalEntry::create([
        'company_id' => $user->company_id,
        'branch_id' => $user->branch_id,
        'journal_number' => 'JE-AEV-CONVERTED',
        'entry_date' => '2026-06-08',
        'summary' => 'Converted placeholder',
        'status' => 'draft',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    $event = aeVoidMakeEvent($user, [
        'status' => 'converted',
        'converted_journal_entry_id' => $journal->id,
    ]);

    $this->actingAs($user)
        ->patch(aeVoidRoute($event), ['void_reason' => 'Should fail'])
        ->assertForbidden();

    $event->refresh();

    expect($event->status)->toBe('converted')
        ->and($event->voided_at)->toBeNull();
});

it('already voided accounting event cannot be voided again', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-already@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.void']);
    $originalVoidedAt = now()->subDay()->setMicrosecond(0);
    $event = aeVoidMakeEvent($user, [
        'status' => 'voided',
        'voided_at' => $originalVoidedAt,
        'voided_by' => $user->id,
        'void_reason' => 'Original reason',
    ]);

    $this->actingAs($user)
        ->patch(aeVoidRoute($event), ['void_reason' => 'Overwrite attempt'])
        ->assertForbidden();

    $event->refresh();

    expect($event->status)->toBe('voided')
        ->and($event->void_reason)->toBe('Original reason')
        ->and($event->voided_by)->toBe($user->id)
        ->and($event->voided_at?->toDateTimeString())->toBe($originalVoidedAt->toDateTimeString());
});

it('void request requires void_reason', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-validation@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.void']);
    $event = aeVoidMakeEvent($user);

    $this->actingAs($user)
        ->from(route('employee-system.accounting.events.show', $event->id))
        ->patch(aeVoidRoute($event), ['void_reason' => ''])
        ->assertSessionHasErrors('void_reason');

    expect($event->fresh()->status)->toBe('pending');
});

it('void request rejects system and accounting payload fields', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-deny-list@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.void']);
    $event = aeVoidMakeEvent($user, ['amount' => 100000]);

    $this->actingAs($user)
        ->patch(aeVoidRoute($event), [
            'void_reason' => 'Attempt with forbidden fields',
            'status' => 'voided',
            'amount' => 1,
            'payload' => ['profit' => 999],
            'company_id' => 999,
            'branch_id' => 999,
            'reviewed_by' => 999,
            'reviewed_at' => now()->toDateTimeString(),
            'converted_journal_entry_id' => 999,
            'voided_by' => 999,
            'voided_at' => now()->toDateTimeString(),
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
        ->and($event->voided_by)->toBeNull()
        ->and($event->voided_at)->toBeNull()
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and($event->payload)->toBe(['vehicle_stock_number' => 'STK-AEV-001', 'receivable_status' => 'paid']);
});

it('void writes audit log without sensitive payload', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-audit@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.void']);
    $event = aeVoidMakeEvent($user, [
        'payload' => [
            'vehicle_stock_number' => 'STK-AEV-AUDIT',
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
        ->patch(aeVoidRoute($event), ['void_reason' => 'Audit safe void'])
        ->assertRedirect();

    $log = ActivityLog::query()->latest('id')->firstOrFail();
    $json = json_encode([
        'metadata' => $log->metadata,
        'old_values' => $log->old_values,
        'new_values' => $log->new_values,
    ], JSON_THROW_ON_ERROR);

    expect($log->action)->toBe('accounting_event.voided')
        ->and($log->event)->toBe('accounting_event.voided')
        ->and($log->description)->toBe('Accounting event voided')
        ->and($log->old_values['old_status'] ?? null)->toBe('pending')
        ->and($log->new_values['new_status'] ?? null)->toBe('voided')
        ->and($log->new_values['void_reason'] ?? null)->toBe('Audit safe void')
        ->and($log->new_values['voided_at'] ?? null)->not->toBeNull();

    foreach (['payload', 'customer_phone', 'id_number', 'birthday', 'address', 'profit', 'gross_profit', 'gross_margin', 'cogs_amount', 'revenue_amount', 'company_id', 'branch_id'] as $forbidden) {
        expect($json)->not->toContain($forbidden);
    }
});

it('show page exposes can.void only when authorized and voidable', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $voidUser = aeVoidMakeUser('aev-show-void@example.com');
    $voidUser->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.void']);
    $viewUser = aeVoidMakeUser('aev-show-view@example.com');
    $viewUser->givePermissionTo('module.accounting.events.view');
    $pending = aeVoidMakeEvent($voidUser, ['source_number' => 'AEV-CAN-PENDING']);
    $reviewed = aeVoidMakeEvent($voidUser, ['source_number' => 'AEV-CAN-REVIEWED', 'status' => 'reviewed']);
    $converted = aeVoidMakeEvent($voidUser, ['source_number' => 'AEV-CAN-CONVERTED', 'status' => 'converted']);
    $voided = aeVoidMakeEvent($voidUser, ['source_number' => 'AEV-CAN-VOIDED', 'status' => 'voided', 'voided_at' => now(), 'voided_by' => $voidUser->id, 'void_reason' => 'Already voided']);

    foreach ([$pending, $reviewed] as $event) {
        $this->actingAs($voidUser)
            ->get(route('employee-system.accounting.events.show', $event->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.void', true));
    }

    foreach ([$converted, $voided] as $event) {
        $this->actingAs($voidUser)
            ->get(route('employee-system.accounting.events.show', $event->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.void', false));
    }

    $this->actingAs($viewUser)
        ->get(route('employee-system.accounting.events.show', $pending->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.void', false));
});

it('RolePermissionSeeder registers accounting events void permission', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = Role::findByName('admin', 'web');
    $accounting = Role::findByName('accounting', 'web');
    $viewer = Role::findByName('viewer', 'web');

    expect(Permission::query()->where('name', 'module.accounting.events.void')->exists())->toBeTrue()
        ->and($admin->hasPermissionTo('module.accounting.events.void'))->toBeTrue()
        ->and($accounting->hasPermissionTo('module.accounting.events.void'))->toBeTrue()
        ->and($viewer->hasPermissionTo('module.accounting.events.void'))->toBeFalse()
        ->and(Permission::query()->where('name', 'module.accounting.events.convert')->exists())->toBeFalse();
});

it('Staff permission matrix displays accounting events void action', function (): void {
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
                    && ($matrix['accounting.events']['actions']['review']['permission'] ?? null) === 'module.accounting.events.review'
                    && ($matrix['accounting.events']['actions']['void']['permission'] ?? null) === 'module.accounting.events.void';
            })
            ->where('actionLabels.void', '作廢')
        );
});

it('void route does not create journal draft or journal lines', function (): void {
    aeVoidRegisterAccountingEventsModule();
    $user = aeVoidMakeUser('aev-no-journal@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.void']);
    $event = aeVoidMakeEvent($user);

    $this->actingAs($user)
        ->patch(aeVoidRoute($event), ['void_reason' => 'No journal draft'])
        ->assertRedirect();

    expect(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});
