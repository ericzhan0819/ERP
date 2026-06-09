<?php

use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalEntryLine;
use App\Models\User;
use App\Services\AccountingEventConvertPreflightService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('module.accounting.events.convert', 'web');
    Permission::findOrCreate('module.accounting.journals.create', 'web');
});

function aePreflightEnsureTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        [
            'name' => 'AE Preflight Company '.$companyId,
            'code' => 'AEP'.$companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            [
                'company_id' => $companyId,
                'name' => 'AE Preflight Branch '.$branchId,
                'code' => 'AEPB'.$companyId.'-'.$branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

function aePreflightMakeUser(array $permissions = ['module.accounting.events.convert', 'module.accounting.journals.create'], int $companyId = 1, ?int $branchId = 10): User
{
    aePreflightEnsureTenantRows($companyId, $branchId);

    $user = User::create([
        'name' => 'AE Preflight User',
        'email' => 'ae-preflight-'.uniqid().'@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);

    $user->givePermissionTo($permissions);

    return $user;
}

function aePreflightMakeReviewedEvent(User $user, array $overrides = []): AccountingEvent
{
    return AccountingEvent::create(array_merge([
        'company_id' => $user->company_id,
        'branch_id' => $user->branch_id,
        'source_type' => 'vehicle_sale_completion',
        'source_id' => 9101,
        'source_number' => 'SALE-AEP-001',
        'event_type' => 'vehicle_sale_completed',
        'event_date' => '2026-06-09',
        'status' => 'reviewed',
        'currency' => 'TWD',
        'amount' => 120000,
        // 技術註解：測試 payload 故意包含敏感與認列欄位，確認 preview 不回傳完整 payload 或衍生毛利/成本資料。
        'payload' => [
            'vehicle_stock_number' => 'STK-AEP-001',
            'customer_phone' => '0900000000',
            'profit' => 999,
            'gross_margin' => 0.5,
            'purchase_cost' => 1,
            'cogs_amount' => 1,
        ],
        'created_by' => $user->id,
        'reviewed_by' => $user->id,
        'reviewed_at' => now()->subHour()->setMicrosecond(0),
        'review_note' => 'Reviewed for preflight.',
    ], $overrides));
}

function aePreflightMakeAccount(User $user, string $type, array $overrides = []): AccountingAccount
{
    return AccountingAccount::create(array_merge([
        'company_id' => $user->company_id,
        'branch_id' => null,
        'code' => 'AEP-'.strtoupper($type).'-'.uniqid(),
        'name' => 'AE Preflight '.$type,
        'type' => $type,
        'opening_balance' => 0,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

function aePreflightEnableMapping(AccountingAccount $receivable, AccountingAccount $revenue, array $overrides = []): void
{
    $mapping = config('accounting_event_mappings.event_types.vehicle_sale_completed');
    $mapping['enabled'] = true;
    $mapping['mapping_keys']['accounts_receivable_account']['runtime_account_id'] = $receivable->id;
    $mapping['mapping_keys']['sales_revenue_account']['runtime_account_id'] = $revenue->id;

    config(['accounting_event_mappings.event_types.vehicle_sale_completed' => array_replace_recursive($mapping, $overrides)]);
}

function aePreflightPreview(AccountingEvent $event, User $user): array
{
    return app(AccountingEventConvertPreflightService::class)->preview($event, $user);
}

function aePreflightAssertNoMutation(AccountingEvent $event, string $status = 'reviewed'): void
{
    $event->refresh();

    expect($event->status)->toBe($status)
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
}

function aePreflightExpectValidationMessage(callable $callback, string $message): void
{
    try {
        $callback();
        expect(false)->toBeTrue('Expected validation exception was not thrown.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->all())->toContain($message);
    }
}

it('mapping disabled fails safe without journal line or event mutation', function (): void {
    $user = aePreflightMakeUser();
    $event = aePreflightMakeReviewedEvent($user);

    aePreflightExpectValidationMessage(
        fn () => aePreflightPreview($event, $user),
        '會計事件映射尚未啟用，無法產生傳票草稿。'
    );

    aePreflightAssertNoMutation($event);
});

it('missing mapping returns 422 message without mutation', function (): void {
    $user = aePreflightMakeUser();
    $event = aePreflightMakeReviewedEvent($user, ['event_type' => 'missing_mapping_event']);

    aePreflightExpectValidationMessage(
        fn () => aePreflightPreview($event, $user),
        '找不到會計事件映射設定，無法產生傳票草稿。'
    );

    aePreflightAssertNoMutation($event);
});

it('source type mismatch returns 422 message without mutation', function (): void {
    $user = aePreflightMakeUser();
    $event = aePreflightMakeReviewedEvent($user, ['source_type' => 'wrong_source']);

    aePreflightExpectValidationMessage(
        fn () => aePreflightPreview($event, $user),
        '會計事件映射與來源類型不一致，無法產生傳票草稿。'
    );

    aePreflightAssertNoMutation($event);
});

it('user missing journal create permission is rejected', function (): void {
    $user = aePreflightMakeUser(['module.accounting.events.convert']);
    $event = aePreflightMakeReviewedEvent($user);

    aePreflightExpectValidationMessage(
        fn () => aePreflightPreview($event, $user),
        '沒有建立會計傳票的權限。'
    );

    aePreflightAssertNoMutation($event);
});

it('pending voided and converted events are rejected', function (string $case, string $status, string $message): void {
    $user = aePreflightMakeUser();
    $overrides = match ($case) {
        'pending' => ['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null],
        'voided' => ['status' => 'voided', 'voided_at' => now(), 'voided_by' => 1, 'void_reason' => 'voided'],
        'converted' => [
            'status' => 'converted',
            'converted_journal_entry_id' => AccountingJournalEntry::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'journal_number' => 'JE-AEP-CONVERTED-'.uniqid(),
                'entry_date' => '2026-06-09',
                'summary' => 'Existing draft placeholder',
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->id,
        ],
    };
    $event = aePreflightMakeReviewedEvent($user, $overrides);

    aePreflightExpectValidationMessage(fn () => aePreflightPreview($event, $user), $message);

    $event->refresh();
    expect($event->status)->toBe($status);
    expect(AccountingJournalEntry::count())->toBe($event->converted_journal_entry_id === null ? 0 : 1);
    expect(AccountingJournalEntryLine::count())->toBe(0);
})->with([
    'pending' => ['pending', 'pending', '只有已覆核的會計事件可以產生傳票草稿。'],
    'voided' => ['voided', 'voided', '只有已覆核的會計事件可以產生傳票草稿。'],
    'converted' => ['converted', 'converted', '此會計事件已產生傳票草稿。'],
]);

it('amount less than or equal to zero is rejected', function (int $amount): void {
    $user = aePreflightMakeUser();
    $event = aePreflightMakeReviewedEvent($user, ['amount' => $amount]);

    aePreflightExpectValidationMessage(
        fn () => aePreflightPreview($event, $user),
        '會計事件金額必須大於 0。'
    );

    aePreflightAssertNoMutation($event);
})->with([0, -1]);

it('enabled mapping with valid runtime accounts returns preview header and two revenue side lines only', function (): void {
    $user = aePreflightMakeUser();
    $receivable = aePreflightMakeAccount($user, 'asset');
    $revenue = aePreflightMakeAccount($user, 'revenue');
    aePreflightEnableMapping($receivable, $revenue);
    $event = aePreflightMakeReviewedEvent($user, ['amount' => 150000]);

    $preview = aePreflightPreview($event, $user);

    expect($preview['header'])->toMatchArray([
        'company_id' => $event->company_id,
        'branch_id' => $event->branch_id,
        'entry_date' => '2026-06-09',
        'status' => 'draft',
        'source_type' => 'accounting_event',
        'source_id' => $event->id,
        'summary' => '車輛交易完成轉傳票：SALE-AEP-001',
    ])->and($preview['lines'])->toHaveCount(2)
        ->and($preview['amount'])->toBe(150000.0)
        ->and($preview['event_id'])->toBe($event->id)
        ->and($preview['source_number'])->toBe('SALE-AEP-001')
        ->and($preview['event_type'])->toBe('vehicle_sale_completed');

    aePreflightAssertNoMutation($event);
});

it('preview debit and credit lines use required mapping accounts and stay balanced', function (): void {
    $user = aePreflightMakeUser();
    $receivable = aePreflightMakeAccount($user, 'asset');
    $revenue = aePreflightMakeAccount($user, 'revenue');
    aePreflightEnableMapping($receivable, $revenue);
    $event = aePreflightMakeReviewedEvent($user, ['amount' => 230000]);

    $preview = aePreflightPreview($event, $user);
    $debitLine = $preview['lines'][0];
    $creditLine = $preview['lines'][1];

    expect($debitLine)->toMatchArray([
        'account_id' => $receivable->id,
        'debit' => 230000.0,
        'credit' => 0.0,
        'sort_order' => 1,
    ])->and($creditLine)->toMatchArray([
        'account_id' => $revenue->id,
        'debit' => 0.0,
        'credit' => 230000.0,
        'sort_order' => 2,
    ])->and($preview['total_debit'])->toBe($preview['total_credit'])
        ->and(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
});

it('invalid mapped accounts are rejected', function (callable $accountFactory): void {
    $user = aePreflightMakeUser();
    $validRevenue = aePreflightMakeAccount($user, 'revenue');
    $invalidReceivable = $accountFactory($user);
    aePreflightEnableMapping($invalidReceivable, $validRevenue);
    $event = aePreflightMakeReviewedEvent($user);

    aePreflightExpectValidationMessage(
        fn () => aePreflightPreview($event, $user),
        '會計事件映射科目無效，無法產生傳票草稿。'
    );

    aePreflightAssertNoMutation($event);
})->with([
    'inactive account' => [fn (User $user): AccountingAccount => aePreflightMakeAccount($user, 'asset', ['is_active' => false])],
    'wrong company account' => [function (User $user): AccountingAccount {
        aePreflightEnsureTenantRows(2, 20);

        return aePreflightMakeAccount($user, 'asset', ['company_id' => 2, 'branch_id' => 20]);
    }],
    'wrong branch account' => [function (User $user): AccountingAccount {
        aePreflightEnsureTenantRows((int) $user->company_id, 20);

        return aePreflightMakeAccount($user, 'asset', ['branch_id' => 20]);
    }],
    'wrong account type' => [fn (User $user): AccountingAccount => aePreflightMakeAccount($user, 'expense')],
]);

it('missing required runtime account id is rejected', function (): void {
    $user = aePreflightMakeUser();
    $receivable = aePreflightMakeAccount($user, 'asset');
    $revenue = aePreflightMakeAccount($user, 'revenue');
    aePreflightEnableMapping($receivable, $revenue, [
        'mapping_keys' => [
            'sales_revenue_account' => ['runtime_account_id' => null],
        ],
    ]);
    $event = aePreflightMakeReviewedEvent($user);

    aePreflightExpectValidationMessage(
        fn () => aePreflightPreview($event, $user),
        '會計事件映射尚未指定必要科目，無法產生傳票草稿。'
    );

    aePreflightAssertNoMutation($event);
});

it('preview excludes sensitive recognition keys and non revenue side lines', function (): void {
    $user = aePreflightMakeUser();
    $receivable = aePreflightMakeAccount($user, 'asset');
    $revenue = aePreflightMakeAccount($user, 'revenue');
    aePreflightEnableMapping($receivable, $revenue);
    $event = aePreflightMakeReviewedEvent($user);

    $preview = aePreflightPreview($event, $user);
    $encoded = json_encode($preview, JSON_THROW_ON_ERROR);

    foreach (['profit', 'gross_profit', 'gross_margin', 'gross_margin_rate', 'purchase_cost', 'cogs_amount', 'revenue_amount', 'customer_phone', 'id_number', 'birthday', 'address', 'payload'] as $forbiddenKey) {
        expect($encoded)->not->toContain($forbiddenKey);
    }

    foreach (['vehicle_inventory_account', 'cogs_account', 'tax_payable_account', 'overpayment_account', 'rounding_adjustment_account'] as $nonRevenueKey) {
        expect($encoded)->not->toContain($nonRevenueKey);
    }

    expect($preview['lines'])->toHaveCount(2);
    aePreflightAssertNoMutation($event);
});
