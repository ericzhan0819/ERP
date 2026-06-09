<?php

use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalEntryLine;
use App\Models\User;
use App\Services\AccountingEventJournalDraftPreflightService;
use App\Services\AccountingJournalValidator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'module.accounting.events.convert',
        'module.accounting.journals.create',
        'module.accounting.view',
        'module.accounting.events.review',
        'module.accounting.events.void',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

function aeDraftPreflightEnsureTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'AE Draft Preflight Company '.$companyId,
            'code' => 'AEDP'.$companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            [
                'company_id' => $companyId,
                'name' => 'AE Draft Preflight Branch '.$branchId,
                'code' => 'AEDPB'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function aeDraftPreflightMakeUser(array $permissions = ['module.accounting.events.convert', 'module.accounting.journals.create'], int $companyId = 1, ?int $branchId = 10): User
{
    aeDraftPreflightEnsureTenantRows($companyId, $branchId);

    $user = User::create([
        'name' => 'AE Draft Preflight User',
        'email' => 'ae-draft-preflight-'.uniqid().'@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function aeDraftPreflightMakeReviewedEvent(User $user, array $overrides = []): AccountingEvent
{
    return AccountingEvent::create(array_merge([
        'company_id' => $user->company_id,
        'branch_id' => $user->branch_id,
        'source_type' => 'vehicle_sale_completion',
        'source_id' => 9201,
        'source_number' => 'SALE-AEDP-001',
        'event_type' => 'vehicle_sale_completed',
        'event_date' => '2026-06-09',
        'status' => 'reviewed',
        'currency' => 'TWD',
        'amount' => 120000,
        // 技術註解：payload 故意放入敏感與認列欄位，確認 preflight preview 只輸出安全 allowlist。
        'payload' => [
            'vehicle_stock_number' => 'STK-AEDP-001',
            'customer_phone' => '0900000000',
            'id_number' => 'A123456789',
            'birthday' => '1990-01-01',
            'address' => 'Hidden address',
            'purchase_cost' => 1,
            'cogs_amount' => 1,
            'revenue_amount' => 1,
            'profit' => 1,
            'gross_profit' => 1,
            'gross_margin' => 1,
            'gross_margin_rate' => 1,
        ],
        'created_by' => $user->id,
        'reviewed_by' => $user->id,
        'reviewed_at' => now()->subHour()->setMicrosecond(0),
        'review_note' => 'Reviewed for draft preflight.',
    ], $overrides));
}

function aeDraftPreflightMakeAccount(User $user, string $type, array $overrides = []): AccountingAccount
{
    return AccountingAccount::create(array_merge([
        'company_id' => $user->company_id,
        'branch_id' => null,
        'code' => 'AEDP-'.strtoupper($type).'-'.uniqid(),
        'name' => 'AE Draft Preflight '.$type,
        'type' => $type,
        'opening_balance' => 0,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

function aeDraftPreflightSetMapping(bool $enabled, ?int $receivableId = null, ?int $revenueId = null, array $overrides = []): void
{
    $mapping = config('accounting_event_mappings.event_types.vehicle_sale_completed');
    $mapping['enabled'] = $enabled;
    $mapping['mapping_keys']['accounts_receivable_account']['runtime_account_id'] = $receivableId;
    $mapping['mapping_keys']['sales_revenue_account']['runtime_account_id'] = $revenueId;

    Config::set('accounting_event_mappings.event_types.vehicle_sale_completed', array_replace_recursive($mapping, $overrides));
}

function aeDraftPreflightPreview(AccountingEvent $event, User $user): array
{
    return app(AccountingEventJournalDraftPreflightService::class)->preview($event, $user);
}

function aeDraftPreflightAssertNoMutation(AccountingEvent $event, string $status = 'reviewed', ?int $journalCount = 0): void
{
    $event->refresh();

    expect($event->status)->toBe($status)
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and(AccountingJournalEntry::count())->toBe($journalCount)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
}

function aeDraftPreflightExpectValidationMessage(callable $callback, string $message): void
{
    try {
        $callback();
        expect(false)->toBeTrue('Expected validation exception was not thrown.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->all())->toContain($message);
    }
}

it('mapping disabled fails safe without journal line or event mutation', function (): void {
    $user = aeDraftPreflightMakeUser();
    $event = aeDraftPreflightMakeReviewedEvent($user);

    aeDraftPreflightExpectValidationMessage(
        fn () => aeDraftPreflightPreview($event, $user),
        '會計事件映射尚未啟用，無法產生傳票草稿。'
    );

    aeDraftPreflightAssertNoMutation($event);
});

it('requires convert and journal create permissions without substitutes', function (array $permissions, string $message, bool $forbidden): void {
    $user = aeDraftPreflightMakeUser($permissions);
    $event = aeDraftPreflightMakeReviewedEvent($user);

    if ($forbidden) {
        $this->expectException(Symfony\Component\HttpKernel\Exception\HttpException::class);
        aeDraftPreflightPreview($event, $user);
    } else {
        aeDraftPreflightExpectValidationMessage(fn () => aeDraftPreflightPreview($event, $user), $message);
        aeDraftPreflightAssertNoMutation($event);
    }
})->with([
    'no convert' => [['module.accounting.journals.create'], '', true],
    'convert without journal create' => [['module.accounting.events.convert'], '沒有建立會計傳票的權限。', false],
    'accounting view only' => [['module.accounting.view'], '', true],
    'review only' => [['module.accounting.events.review'], '', true],
    'void only' => [['module.accounting.events.void'], '', true],
]);

it('rejects pending and voided event status without writing database', function (array $overrides, string $status, string $message): void {
    $user = aeDraftPreflightMakeUser();
    $event = aeDraftPreflightMakeReviewedEvent($user, $overrides);

    aeDraftPreflightExpectValidationMessage(fn () => aeDraftPreflightPreview($event, $user), $message);

    aeDraftPreflightAssertNoMutation($event, $status);
})->with([
    'pending' => [['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null], 'pending', '只有已覆核的會計事件可以產生傳票草稿。'],
    'voided status' => [['status' => 'voided', 'voided_at' => now(), 'voided_by' => 1, 'void_reason' => 'voided'], 'voided', '已作廢的會計事件不可產生傳票草稿。'],
]);

it('rejects converted event status without writing additional database rows', function (): void {
    $user = aeDraftPreflightMakeUser();
    $journal = AccountingJournalEntry::create([
        'company_id' => $user->company_id,
        'branch_id' => $user->branch_id,
        'journal_number' => 'JE-AEDP-CONVERTED-'.uniqid(),
        'entry_date' => '2026-06-09',
        'summary' => 'Existing draft placeholder',
        'status' => 'draft',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    $event = aeDraftPreflightMakeReviewedEvent($user, [
        'status' => 'converted',
        'converted_journal_entry_id' => $journal->id,
    ]);

    aeDraftPreflightExpectValidationMessage(fn () => aeDraftPreflightPreview($event, $user), '此會計事件已產生傳票草稿。');

    $event->refresh();
    expect($event->status)->toBe('converted')
        ->and($event->converted_journal_entry_id)->toBe($journal->id)
        ->and(AccountingJournalEntry::count())->toBe(1)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});

it('rejects converted journal id even when status is reviewed', function (): void {
    $user = aeDraftPreflightMakeUser();
    $journal = AccountingJournalEntry::create([
        'company_id' => $user->company_id,
        'branch_id' => $user->branch_id,
        'journal_number' => 'JE-AEDP-EXISTING',
        'entry_date' => '2026-06-09',
        'summary' => 'Existing draft placeholder',
        'status' => 'draft',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    $event = aeDraftPreflightMakeReviewedEvent($user, ['converted_journal_entry_id' => $journal->id]);

    aeDraftPreflightExpectValidationMessage(fn () => aeDraftPreflightPreview($event, $user), '此會計事件已產生傳票草稿。');

    $event->refresh();
    expect($event->status)->toBe('reviewed')
        ->and($event->converted_journal_entry_id)->toBe($journal->id)
        ->and(AccountingJournalEntry::count())->toBe(1)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});

it('enforces tenant scope as not found instead of forbidden', function (User $user, AccountingEvent $event): void {
    $this->expectException(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

    aeDraftPreflightPreview($event, $user);
})->with([
    'cross company' => function (): array {
        $user = aeDraftPreflightMakeUser(companyId: 1, branchId: 10);
        $owner = aeDraftPreflightMakeUser(companyId: 2, branchId: 20);

        return [$user, aeDraftPreflightMakeReviewedEvent($owner)];
    },
    'other branch' => function (): array {
        $user = aeDraftPreflightMakeUser(companyId: 1, branchId: 10);
        $owner = aeDraftPreflightMakeUser(companyId: 1, branchId: 20);

        return [$user, aeDraftPreflightMakeReviewedEvent($owner)];
    },
]);

it('allows branch user to preflight company level event before mapping disabled check', function (): void {
    $user = aeDraftPreflightMakeUser(companyId: 1, branchId: 10);
    $event = aeDraftPreflightMakeReviewedEvent($user, ['branch_id' => null]);

    aeDraftPreflightExpectValidationMessage(
        fn () => aeDraftPreflightPreview($event, $user),
        '會計事件映射尚未啟用，無法產生傳票草稿。'
    );

    aeDraftPreflightAssertNoMutation($event);
});

it('enabled mapping without runtime account ids is rejected without writing database', function (): void {
    $user = aeDraftPreflightMakeUser();
    $event = aeDraftPreflightMakeReviewedEvent($user);
    aeDraftPreflightSetMapping(true);

    aeDraftPreflightExpectValidationMessage(
        fn () => aeDraftPreflightPreview($event, $user),
        '會計事件映射尚未指定必要科目，無法產生傳票草稿。'
    );

    aeDraftPreflightAssertNoMutation($event);
});

it('rejects invalid runtime account mapping', function (callable $accountFactory, string $message): void {
    $user = aeDraftPreflightMakeUser();
    $invalidReceivable = $accountFactory($user);
    $revenue = aeDraftPreflightMakeAccount($user, 'revenue');
    $event = aeDraftPreflightMakeReviewedEvent($user);
    aeDraftPreflightSetMapping(true, $invalidReceivable instanceof AccountingAccount ? $invalidReceivable->id : 999999, $revenue->id);

    aeDraftPreflightExpectValidationMessage(fn () => aeDraftPreflightPreview($event, $user), $message);

    aeDraftPreflightAssertNoMutation($event);
})->with([
    'missing account' => [fn (User $user): null => null, '會計事件映射科目無效，無法產生傳票草稿。'],
    'inactive account' => [fn (User $user): AccountingAccount => aeDraftPreflightMakeAccount($user, 'asset', ['is_active' => false]), '會計事件映射科目無效，無法產生傳票草稿。'],
    'other company' => [function (User $user): AccountingAccount {
        aeDraftPreflightEnsureTenantRows(2, 20);

        return aeDraftPreflightMakeAccount($user, 'asset', ['company_id' => 2, 'branch_id' => 20]);
    }, '會計事件映射科目無效，無法產生傳票草稿。'],
    'branch mismatch' => [function (User $user): AccountingAccount {
        aeDraftPreflightEnsureTenantRows((int) $user->company_id, 20);

        return aeDraftPreflightMakeAccount($user, 'asset', ['branch_id' => 20]);
    }, '會計事件映射科目無效，無法產生傳票草稿。'],
    'wrong type' => [fn (User $user): AccountingAccount => aeDraftPreflightMakeAccount($user, 'expense'), '會計事件映射科目類型不符，無法產生傳票草稿。'],
]);

it('returns valid revenue side preview without sensitive or recognition fields', function (): void {
    $user = aeDraftPreflightMakeUser();
    $receivable = aeDraftPreflightMakeAccount($user, 'asset');
    $revenue = aeDraftPreflightMakeAccount($user, 'revenue');
    $event = aeDraftPreflightMakeReviewedEvent($user, ['amount' => 1000]);
    aeDraftPreflightSetMapping(true, $receivable->id, $revenue->id);

    $preview = aeDraftPreflightPreview($event, $user);

    expect($preview)->toMatchArray([
        'event_id' => $event->id,
        'event_type' => 'vehicle_sale_completed',
        'source_type' => 'vehicle_sale_completion',
        'source_id' => $event->source_id,
        'source_number' => 'SALE-AEDP-001',
        'entry_date' => '2026-06-09',
        'summary' => '車輛交易完成轉傳票：SALE-AEDP-001',
        'status' => 'draft',
        'source' => ['type' => 'accounting_event', 'id' => $event->id],
        'totals' => ['debit' => '1000.00', 'credit' => '1000.00', 'difference' => '0.00'],
        'warnings' => [],
    ])->and($preview['lines'])->toHaveCount(2)
        ->and($preview['lines'][0])->toMatchArray([
            'mapping_key' => 'accounts_receivable_account',
            'account_id' => $receivable->id,
            'account_code' => $receivable->code,
            'account_name' => $receivable->name,
            'account_type' => 'asset',
            'debit' => '1000.00',
            'credit' => '0.00',
            'memo' => '應收帳款／收款清算',
            'sort_order' => 0,
        ])->and($preview['lines'][1])->toMatchArray([
            'mapping_key' => 'sales_revenue_account',
            'account_id' => $revenue->id,
            'account_code' => $revenue->code,
            'account_name' => $revenue->name,
            'account_type' => 'revenue',
            'debit' => '0.00',
            'credit' => '1000.00',
            'memo' => '車輛銷售收入',
            'sort_order' => 1,
        ]);

    $encoded = json_encode($preview, JSON_THROW_ON_ERROR);

    foreach (['customer_phone', 'id_number', 'birthday', 'address', 'company_id', 'branch_id', 'purchase_cost', 'cogs_amount', 'revenue_amount', 'profit', 'gross_profit', 'gross_margin', 'gross_margin_rate', 'payload', 'journal_number'] as $forbiddenKey) {
        expect($encoded)->not->toContain($forbiddenKey);
    }

    app(AccountingJournalValidator::class)->validateDraftLines($preview['lines'], (int) $event->company_id);
    aeDraftPreflightAssertNoMutation($event);
});

it('valid preview lines are explicitly accepted by journal validator', function (): void {
    $user = aeDraftPreflightMakeUser();
    $receivable = aeDraftPreflightMakeAccount($user, 'asset');
    $revenue = aeDraftPreflightMakeAccount($user, 'revenue');
    $event = aeDraftPreflightMakeReviewedEvent($user, ['amount' => 5000]);
    aeDraftPreflightSetMapping(true, $receivable->id, $revenue->id);

    $preview = aeDraftPreflightPreview($event, $user);

    app(AccountingJournalValidator::class)->validateDraftLines($preview['lines'], (int) $event->company_id);

    aeDraftPreflightAssertNoMutation($event);
});
