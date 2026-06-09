<?php

use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingEventAccountMapping;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalEntryLine;
use App\Models\User;
use App\Services\AccountingEventAccountMappingResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function aeResolverEnsureTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        ['name' => 'AE Resolver Company '.$companyId, 'code' => 'AER'.$companyId, 'created_at' => now(), 'updated_at' => now()]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            ['company_id' => $companyId, 'name' => 'AE Resolver Branch '.$branchId, 'code' => 'AERB'.$companyId.'-'.$branchId, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}

function aeResolverMakeUser(int $companyId = 1, ?int $branchId = 10): User
{
    aeResolverEnsureTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'AE Resolver User',
        'email' => 'ae-resolver-'.uniqid().'@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function aeResolverMakeEvent(User $user, array $overrides = []): AccountingEvent
{
    return AccountingEvent::create(array_merge([
        'company_id' => $user->company_id,
        'branch_id' => $user->branch_id,
        'source_type' => 'vehicle_sale_completion',
        'source_id' => 9201,
        'source_number' => 'SALE-AER-001',
        'event_type' => 'vehicle_sale_completed',
        'event_date' => '2026-06-09',
        'status' => 'reviewed',
        'currency' => 'TWD',
        'amount' => 120000,
        'payload' => [],
        'created_by' => $user->id,
        'reviewed_by' => $user->id,
        'reviewed_at' => now()->subHour()->setMicrosecond(0),
    ], $overrides));
}

function aeResolverMakeAccount(User $user, string $type, array $overrides = []): AccountingAccount
{
    return AccountingAccount::create(array_merge([
        'company_id' => $user->company_id,
        'branch_id' => null,
        'code' => 'AER-'.strtoupper($type).'-'.uniqid(),
        'name' => 'AE Resolver '.$type.' '.uniqid(),
        'type' => $type,
        'opening_balance' => 0,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

function aeResolverCreateMapping(AccountingEvent $event, string $key, AccountingAccount $account, array $overrides = []): AccountingEventAccountMapping
{
    return AccountingEventAccountMapping::create(array_merge([
        'company_id' => $event->company_id,
        'branch_id' => null,
        'event_type' => $event->event_type,
        'source_type' => $event->source_type,
        'mapping_key' => $key,
        'account_id' => $account->id,
        'is_active' => true,
    ], $overrides));
}

function aeResolverMappingConfig(array $overrides = []): array
{
    return array_replace_recursive(config('accounting_event_mappings.event_types.vehicle_sale_completed'), $overrides);
}

function aeResolverResolve(AccountingEvent $event, array $mapping = []): array
{
    return app(AccountingEventAccountMappingResolver::class)->resolveRequiredAccounts($event, $mapping ?: aeResolverMappingConfig());
}

function aeResolverExpectValidationMessage(callable $callback, string $message): void
{
    try {
        $callback();
        expect(false)->toBeTrue('Expected validation exception was not thrown.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->all())->toContain($message);
    }
}

function aeResolverAssertNoMutation(AccountingEvent $event): void
{
    $event->refresh();

    expect($event->status)->toBe('reviewed')
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
}

it('resolves exact branch mapping before company default', function (): void {
    $user = aeResolverMakeUser();
    $event = aeResolverMakeEvent($user);
    $defaultReceivable = aeResolverMakeAccount($user, 'asset');
    $branchReceivable = aeResolverMakeAccount($user, 'asset', ['branch_id' => $event->branch_id]);
    $revenue = aeResolverMakeAccount($user, 'revenue');

    aeResolverCreateMapping($event, 'accounts_receivable_account', $defaultReceivable);
    aeResolverCreateMapping($event, 'accounts_receivable_account', $branchReceivable, ['branch_id' => $event->branch_id]);
    aeResolverCreateMapping($event, 'sales_revenue_account', $revenue);

    $resolved = aeResolverResolve($event);

    expect($resolved['accounts_receivable_account']['account']->id)->toBe($branchReceivable->id);
});

it('falls back to company default when exact branch mapping missing', function (): void {
    $user = aeResolverMakeUser();
    $event = aeResolverMakeEvent($user);
    $receivable = aeResolverMakeAccount($user, 'asset');
    $revenue = aeResolverMakeAccount($user, 'revenue');

    aeResolverCreateMapping($event, 'accounts_receivable_account', $receivable);
    aeResolverCreateMapping($event, 'sales_revenue_account', $revenue);

    expect(aeResolverResolve($event)['accounts_receivable_account']['account']->id)->toBe($receivable->id);
});

it('event branch null only uses company default mapping', function (): void {
    $user = aeResolverMakeUser(1, null);
    aeResolverEnsureTenantRows(1, 10);
    $event = aeResolverMakeEvent($user, ['branch_id' => null]);
    $defaultReceivable = aeResolverMakeAccount($user, 'asset');
    $branchReceivable = aeResolverMakeAccount($user, 'asset', ['branch_id' => 10]);
    $revenue = aeResolverMakeAccount($user, 'revenue');

    aeResolverCreateMapping($event, 'accounts_receivable_account', $defaultReceivable);
    aeResolverCreateMapping($event, 'accounts_receivable_account', $branchReceivable, ['branch_id' => 10]);
    aeResolverCreateMapping($event, 'sales_revenue_account', $revenue);

    expect(aeResolverResolve($event)['accounts_receivable_account']['account']->id)->toBe($defaultReceivable->id);
});

it('rejects missing required mapping', function (): void {
    $user = aeResolverMakeUser();
    $event = aeResolverMakeEvent($user);
    aeResolverCreateMapping($event, 'sales_revenue_account', aeResolverMakeAccount($user, 'revenue'));

    aeResolverExpectValidationMessage(fn () => aeResolverResolve($event), '會計事件映射尚未指定必要科目，無法產生傳票草稿。');
    aeResolverAssertNoMutation($event);
});

it('rejects inactive mapping', function (): void {
    $user = aeResolverMakeUser();
    $event = aeResolverMakeEvent($user);
    aeResolverCreateMapping($event, 'accounts_receivable_account', aeResolverMakeAccount($user, 'asset'), ['is_active' => false]);
    aeResolverCreateMapping($event, 'sales_revenue_account', aeResolverMakeAccount($user, 'revenue'));

    aeResolverExpectValidationMessage(fn () => aeResolverResolve($event), '會計事件映射尚未指定必要科目，無法產生傳票草稿。');
    aeResolverAssertNoMutation($event);
});

it('rejects invalid mapped account cases', function (callable $accountFactory): void {
    $user = aeResolverMakeUser();
    $event = aeResolverMakeEvent($user);
    $invalidReceivable = $accountFactory($user, $event);
    aeResolverCreateMapping($event, 'accounts_receivable_account', $invalidReceivable);
    aeResolverCreateMapping($event, 'sales_revenue_account', aeResolverMakeAccount($user, 'revenue'));

    aeResolverExpectValidationMessage(fn () => aeResolverResolve($event), '會計事件映射科目無效，無法產生傳票草稿。');
    aeResolverAssertNoMutation($event);
})->with([
    'account from another company' => [function (User $user): AccountingAccount {
        aeResolverEnsureTenantRows(2, 20);

        return aeResolverMakeAccount($user, 'asset', ['company_id' => 2, 'branch_id' => 20]);
    }],
    'inactive account' => [fn (User $user): AccountingAccount => aeResolverMakeAccount($user, 'asset', ['is_active' => false])],
    'wrong account type' => [fn (User $user): AccountingAccount => aeResolverMakeAccount($user, 'expense')],
    'branch-specific account from another branch' => [function (User $user): AccountingAccount {
        aeResolverEnsureTenantRows((int) $user->company_id, 20);

        return aeResolverMakeAccount($user, 'asset', ['branch_id' => 20]);
    }],
]);

it('returns labels and AccountingAccount objects for required revenue side accounts', function (): void {
    $user = aeResolverMakeUser();
    $event = aeResolverMakeEvent($user);
    $receivable = aeResolverMakeAccount($user, 'asset');
    $revenue = aeResolverMakeAccount($user, 'revenue');
    aeResolverCreateMapping($event, 'accounts_receivable_account', $receivable);
    aeResolverCreateMapping($event, 'sales_revenue_account', $revenue);

    $resolved = aeResolverResolve($event);

    expect($resolved['accounts_receivable_account']['account'])->toBeInstanceOf(AccountingAccount::class)
        ->and($resolved['accounts_receivable_account']['label'])->toBe('應收帳款／收款清算科目')
        ->and($resolved['sales_revenue_account']['account'])->toBeInstanceOf(AccountingAccount::class)
        ->and($resolved['sales_revenue_account']['label'])->toBe('銷貨收入科目');

    aeResolverAssertNoMutation($event);
});

it('optional mapping keys remain unused in first runtime foundation', function (): void {
    $user = aeResolverMakeUser();
    $event = aeResolverMakeEvent($user);
    $receivable = aeResolverMakeAccount($user, 'asset');
    $revenue = aeResolverMakeAccount($user, 'revenue');
    aeResolverCreateMapping($event, 'accounts_receivable_account', $receivable);
    aeResolverCreateMapping($event, 'sales_revenue_account', $revenue);

    $resolved = aeResolverResolve($event, aeResolverMappingConfig([
        'mapping_keys' => [
            'vehicle_inventory_account' => ['runtime_account_id' => 999999],
        ],
    ]));

    expect(array_keys($resolved))->toBe(['accounts_receivable_account', 'sales_revenue_account']);
    aeResolverAssertNoMutation($event);
});
