<?php

use App\Models\AccountingEvent;
use App\Models\AccountingAccount;
use App\Models\AccountingEventAccountMapping;
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

function aeConvertEnsureTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'AE Convert Company '.$companyId,
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
                'name' => 'AE Convert Branch '.$branchId,
                'code' => 'AECB'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function aeConvertRegisterAccountingEventsModule(): void
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
    Permission::findOrCreate('module.accounting.events.convert', 'web');
}

function aeConvertMakeUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    aeConvertEnsureTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'AE Convert User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function aeConvertMakeReviewedEvent(User $creator, array $overrides = []): AccountingEvent
{
    return AccountingEvent::create(array_merge([
        'company_id' => $creator->company_id,
        'branch_id' => $creator->branch_id,
        'source_type' => 'vehicle_sale_completion',
        'source_id' => 3001,
        'source_number' => 'SALE-AEC-001',
        'event_type' => 'vehicle_sale_completed',
        'event_date' => '2026-06-08',
        'status' => 'reviewed',
        'currency' => 'TWD',
        'amount' => 100000,
        // 技術註解：測試 payload 僅保留可顯示摘要，convert skeleton 不讀取或產生任何認列金額。
        'payload' => [
            'vehicle_stock_number' => 'STK-AEC-001',
            'receivable_status' => 'paid',
        ],
        'created_by' => $creator->id,
        'reviewed_by' => $creator->id,
        'reviewed_at' => now()->subHour()->setMicrosecond(0),
        'review_note' => 'Reviewed for convert skeleton.',
    ], $overrides));
}

function aeConvertMakeAccount(User $user, string $type, array $overrides = []): AccountingAccount
{
    return AccountingAccount::create(array_merge([
        'company_id' => $user->company_id,
        'branch_id' => null,
        'code' => 'AEC-'.strtoupper($type).'-'.uniqid(),
        'name' => 'AE Convert '.$type,
        'type' => $type,
        'opening_balance' => 0,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

function aeConvertCreateMapping(AccountingEvent $event, string $key, AccountingAccount $account): void
{
    AccountingEventAccountMapping::create([
        'company_id' => $event->company_id,
        'branch_id' => null,
        'event_type' => $event->event_type,
        'source_type' => $event->source_type,
        'mapping_key' => $key,
        'account_id' => $account->id,
        'is_active' => true,
    ]);
}

function aeConvertCreateRequiredMappings(AccountingEvent $event, AccountingAccount $receivable, AccountingAccount $revenue): void
{
    aeConvertCreateMapping($event, 'accounts_receivable_account', $receivable);
    aeConvertCreateMapping($event, 'sales_revenue_account', $revenue);
}

function aeConvertRoute(AccountingEvent $event): string
{
    return route('employee-system.accounting.events.convert', $event->id);
}

function aeConvertAssertNoJournalMutation(AccountingEvent $event, string $status, ?int $journalCount = 0, ?int $lineCount = 0): void
{
    $event->refresh();

    expect($event->status)->toBe($status)
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and(AccountingJournalEntry::count())->toBe($journalCount)
        ->and(AccountingJournalEntryLine::count())->toBe($lineCount);
}

it('reviewed accounting event with convert permission fails safe when mapping is disabled', function (): void {
    aeConvertRegisterAccountingEventsModule();
    Permission::findOrCreate('module.accounting.journals.create', 'web');
    $user = aeConvertMakeUser('aec-disabled@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert', 'module.accounting.journals.create']);
    $event = aeConvertMakeReviewedEvent($user);
    $mapping = config('accounting_event_mappings.event_types.vehicle_sale_completed');
    $mapping['enabled'] = false;
    config(['accounting_event_mappings.event_types.vehicle_sale_completed' => $mapping]);
    $reviewedAt = $event->reviewed_at?->toDateTimeString();
    $reviewedBy = $event->reviewed_by;

    $this->actingAs($user)
        ->patch(aeConvertRoute($event))
        ->assertStatus(422)
        ->assertSee('會計事件映射尚未啟用，無法產生傳票草稿。');

    $event->refresh();

    expect($event->status)->toBe('reviewed')
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and($event->reviewed_at?->toDateTimeString())->toBe($reviewedAt)
        ->and($event->reviewed_by)->toBe($reviewedBy)
        ->and(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});

it('reviewed accounting event with valid mappings converts into draft journal through route', function (): void {
    aeConvertRegisterAccountingEventsModule();
    Permission::findOrCreate('module.accounting.journals.create', 'web');
    $user = aeConvertMakeUser('aec-route-success@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert', 'module.accounting.journals.create']);
    $event = aeConvertMakeReviewedEvent($user, ['amount' => 200000]);
    $receivable = aeConvertMakeAccount($user, 'asset');
    $revenue = aeConvertMakeAccount($user, 'revenue');
    aeConvertCreateRequiredMappings($event, $receivable, $revenue);

    $this->actingAs($user)
        ->patch(aeConvertRoute($event))
        ->assertRedirect(route('employee-system.accounting.events.show', $event->id))
        ->assertSessionHas('success', '會計事件已產生傳票草稿。');

    $event->refresh();
    $journal = AccountingJournalEntry::query()->firstOrFail();

    expect(AccountingJournalEntry::count())->toBe(1)
        ->and(AccountingJournalEntryLine::count())->toBe(2)
        ->and($journal->status)->toBe('draft')
        ->and($journal->source_type)->toBe('accounting_event')
        ->and($journal->source_id)->toBe($event->id)
        ->and($event->status)->toBe('converted')
        ->and($event->converted_journal_entry_id)->toBe($journal->id)
        ->and(ActivityLog::query()->where('event', 'accounting_event.converted')->exists())->toBeTrue();
});

it('non convert permissions cannot convert reviewed accounting event', function (array $permissions): void {
    aeConvertRegisterAccountingEventsModule();
    Permission::findOrCreate('module.accounting.view', 'web');
    $user = aeConvertMakeUser('aec-denied-'.str_replace('.', '-', implode('-', $permissions)).'@example.com');
    $user->givePermissionTo($permissions);
    $event = aeConvertMakeReviewedEvent($user);

    $this->actingAs($user)
        ->patch(aeConvertRoute($event))
        ->assertForbidden();

    aeConvertAssertNoJournalMutation($event, 'reviewed');
})->with([
    'view only' => [['module.accounting.events.view']],
    'review only' => [['module.accounting.events.view', 'module.accounting.events.review']],
    'void only' => [['module.accounting.events.view', 'module.accounting.events.void']],
    'module accounting view only' => [['module.accounting.view']],
]);

it('non reviewed events cannot be converted', function (array $overrides, string $expectedStatus): void {
    aeConvertRegisterAccountingEventsModule();
    $user = aeConvertMakeUser('aec-status-'.$expectedStatus.'@example.com');
    Permission::findOrCreate('module.accounting.journals.create', 'web');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert', 'module.accounting.journals.create']);
    $event = aeConvertMakeReviewedEvent($user, $overrides);

    $this->actingAs($user)
        ->patch(aeConvertRoute($event))
        ->assertForbidden();

    aeConvertAssertNoJournalMutation($event, $expectedStatus);
})->with([
    'pending' => [['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null], 'pending'],
    'voided' => [['status' => 'voided', 'voided_at' => now(), 'voided_by' => 1, 'void_reason' => 'Already voided'], 'voided'],
]);

it('converted event cannot be converted again', function (): void {
    aeConvertRegisterAccountingEventsModule();
    $user = aeConvertMakeUser('aec-converted@example.com');
    Permission::findOrCreate('module.accounting.journals.create', 'web');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert', 'module.accounting.journals.create']);
    $journal = AccountingJournalEntry::create([
        'company_id' => $user->company_id,
        'branch_id' => $user->branch_id,
        'journal_number' => 'JE-AEC-CONVERTED',
        'entry_date' => '2026-06-08',
        'summary' => 'Existing converted placeholder',
        'status' => 'draft',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    $event = aeConvertMakeReviewedEvent($user, [
        'status' => 'converted',
        'converted_journal_entry_id' => $journal->id,
    ]);

    $this->actingAs($user)
        ->patch(aeConvertRoute($event))
        ->assertForbidden();

    $event->refresh();

    expect($event->status)->toBe('converted')
        ->and($event->converted_journal_entry_id)->toBe($journal->id)
        ->and(AccountingJournalEntry::count())->toBe(1)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});

it('cross tenant convert returns 404 and does not update event', function (): void {
    aeConvertRegisterAccountingEventsModule();
    $user = aeConvertMakeUser('aec-cross-user@example.com', 1, 10);
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert']);
    $owner = aeConvertMakeUser('aec-cross-owner@example.com', 2, 20);
    $event = aeConvertMakeReviewedEvent($owner);

    $this->actingAs($user)
        ->patch(aeConvertRoute($event))
        ->assertNotFound();

    aeConvertAssertNoJournalMutation($event, 'reviewed');
});

it('convert request rejects forbidden system accounting and sensitive fields', function (): void {
    aeConvertRegisterAccountingEventsModule();
    $user = aeConvertMakeUser('aec-deny-list@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert']);
    $event = aeConvertMakeReviewedEvent($user, ['amount' => 100000]);

    $this->actingAs($user)
        ->patch(aeConvertRoute($event), [
            'status' => 'converted',
            'amount' => 1,
            'payload' => ['profit' => 999],
            'company_id' => 999,
            'branch_id' => 999,
            'reviewed_by' => 999,
            'reviewed_at' => now()->toDateTimeString(),
            'review_note' => 'Overwrite review note',
            'converted_journal_entry_id' => 999,
            'voided_by' => 999,
            'voided_at' => now()->toDateTimeString(),
            'void_reason' => 'Injected void',
            'revenue_amount' => 1,
            'cogs_amount' => 1,
            'gross_profit' => 1,
            'gross_margin' => 1,
            'profit' => 1,
            'journal_entry_id' => 999,
            'journal_entry_number' => 'JE-INJECT',
            'accounting_journal_entry_id' => 999,
            'customer_phone' => '0900000000',
            'id_number' => 'A123456789',
            'birthday' => '1990-01-01',
            'address' => 'Hidden',
        ])
        ->assertForbidden();

    $event->refresh();

    expect($event->status)->toBe('reviewed')
        ->and($event->amount)->toBe('100000.00')
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and($event->payload)->toBe(['vehicle_stock_number' => 'STK-AEC-001', 'receivable_status' => 'paid'])
        ->and(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});

it('missing mapping returns 422 and does not create journal draft', function (): void {
    aeConvertRegisterAccountingEventsModule();
    $user = aeConvertMakeUser('aec-missing@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert']);
    $event = aeConvertMakeReviewedEvent($user, ['event_type' => 'missing_mapping_event']);

    $this->actingAs($user)
        ->patch(aeConvertRoute($event))
        ->assertStatus(422)
        ->assertSee('找不到會計事件映射設定，無法產生傳票草稿。');

    aeConvertAssertNoJournalMutation($event, 'reviewed');
});

it('mapping source type mismatch returns 422 and does not create journal draft', function (): void {
    aeConvertRegisterAccountingEventsModule();
    $user = aeConvertMakeUser('aec-mismatch@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert']);
    $event = aeConvertMakeReviewedEvent($user, ['source_type' => 'wrong_source_type']);

    $this->actingAs($user)
        ->patch(aeConvertRoute($event))
        ->assertStatus(422)
        ->assertSee('會計事件映射與來源類型不一致，無法產生傳票草稿。');

    aeConvertAssertNoJournalMutation($event, 'reviewed');
});

it('show page exposes can.convert only for reviewed event with convert permission', function (): void {
    aeConvertRegisterAccountingEventsModule();
    $convertUser = aeConvertMakeUser('aec-show-convert@example.com');
    $convertUser->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert']);
    $viewUser = aeConvertMakeUser('aec-show-view@example.com');
    $viewUser->givePermissionTo('module.accounting.events.view');
    $reviewed = aeConvertMakeReviewedEvent($convertUser, ['source_number' => 'AEC-CAN-REVIEWED']);
    $pending = aeConvertMakeReviewedEvent($convertUser, ['source_number' => 'AEC-CAN-PENDING', 'status' => 'pending']);
    $voided = aeConvertMakeReviewedEvent($convertUser, ['source_number' => 'AEC-CAN-VOIDED', 'status' => 'voided', 'voided_at' => now(), 'voided_by' => $convertUser->id, 'void_reason' => 'Already voided']);
    $journal = AccountingJournalEntry::create([
        'company_id' => $convertUser->company_id,
        'branch_id' => $convertUser->branch_id,
        'journal_number' => 'JE-AEC-SHOW-CONVERTED',
        'entry_date' => '2026-06-08',
        'summary' => 'Existing converted placeholder for show permission test',
        'status' => 'draft',
        'created_by' => $convertUser->id,
        'updated_by' => $convertUser->id,
    ]);
    $converted = aeConvertMakeReviewedEvent($convertUser, ['source_number' => 'AEC-CAN-CONVERTED', 'status' => 'converted', 'converted_journal_entry_id' => $journal->id]);

    $this->actingAs($convertUser)
        ->get(route('employee-system.accounting.events.show', $reviewed->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.convert', true));

    foreach ([$pending, $voided, $converted] as $event) {
        $this->actingAs($convertUser)
            ->get(route('employee-system.accounting.events.show', $event->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.convert', false));
    }

    $this->actingAs($viewUser)
        ->get(route('employee-system.accounting.events.show', $reviewed->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.convert', false));
});

it('reviewed event show exposes convert availability without converted journal payload', function (): void {
    aeConvertRegisterAccountingEventsModule();
    $user = aeConvertMakeUser('aec-show-reviewed-payload@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert']);
    $event = aeConvertMakeReviewedEvent($user, ['source_number' => 'AEC-SHOW-REVIEWED']);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.events.show', $event->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.convert', true)
            ->where('event.status', 'reviewed')
            ->where('event.converted_journal_entry', null)
        );
});

it('converted event show payload includes converted journal draft reference', function (): void {
    aeConvertRegisterAccountingEventsModule();
    $user = aeConvertMakeUser('aec-show-converted-payload@example.com');
    $user->givePermissionTo('module.accounting.events.view');
    $journal = AccountingJournalEntry::create([
        'company_id' => $user->company_id,
        'branch_id' => $user->branch_id,
        'journal_number' => 'JE-AEC-SHOW-LINK',
        'entry_date' => '2026-06-08',
        'summary' => 'Converted link payload',
        'status' => 'draft',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    $event = aeConvertMakeReviewedEvent($user, ['status' => 'converted', 'converted_journal_entry_id' => $journal->id]);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.events.show', $event->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.convert', false)
            ->where('event.converted_journal_entry.id', $journal->id)
            ->where('event.converted_journal_entry.journal_number', 'JE-AEC-SHOW-LINK')
            ->where('event.converted_journal_entry.status', 'draft')
            ->where('event.converted_journal_entry.entry_date', '2026-06-08')
        );
});

it('successful convert exposes converted journal link payload on event show', function (): void {
    aeConvertRegisterAccountingEventsModule();
    Permission::findOrCreate('module.accounting.journals.create', 'web');
    $user = aeConvertMakeUser('aec-show-after-convert@example.com');
    $user->givePermissionTo(['module.accounting.events.view', 'module.accounting.events.convert', 'module.accounting.journals.create']);
    $event = aeConvertMakeReviewedEvent($user, ['amount' => 210000]);
    $receivable = aeConvertMakeAccount($user, 'asset');
    $revenue = aeConvertMakeAccount($user, 'revenue');
    aeConvertCreateRequiredMappings($event, $receivable, $revenue);

    $this->actingAs($user)
        ->patch(aeConvertRoute($event))
        ->assertRedirect(route('employee-system.accounting.events.show', $event->id));

    $event->refresh();
    $journal = AccountingJournalEntry::query()->findOrFail($event->converted_journal_entry_id);

    $this->actingAs($user)
        ->get(route('employee-system.accounting.events.show', $event->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('event.status', 'converted')
            ->where('event.converted_journal_entry.id', $journal->id)
            ->where('event.converted_journal_entry.journal_number', $journal->journal_number)
            ->where('event.converted_journal_entry.status', 'draft')
            ->where('event.converted_journal_entry.entry_date', '2026-06-08')
        );
});

it('RolePermissionSeeder registers accounting events convert permission', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = Role::findByName('admin', 'web');
    $accounting = Role::findByName('accounting', 'web');
    $viewer = Role::findByName('viewer', 'web');

    expect(Permission::query()->where('name', 'module.accounting.events.convert')->exists())->toBeTrue()
        ->and($admin->hasPermissionTo('module.accounting.events.convert'))->toBeTrue()
        ->and($accounting->hasPermissionTo('module.accounting.events.convert'))->toBeTrue()
        ->and($viewer->hasPermissionTo('module.accounting.events.convert'))->toBeFalse()
        ->and($viewer->hasPermissionTo('module.accounting.view'))->toBeFalse();
});

it('Staff permission matrix displays accounting events convert action', function (): void {
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
                    && ($matrix['accounting.events']['actions']['void']['permission'] ?? null) === 'module.accounting.events.void'
                    && ($matrix['accounting.events']['actions']['convert']['permission'] ?? null) === 'module.accounting.events.convert';
            })
            ->where('actionLabels.convert', '轉傳票')
        );
});
