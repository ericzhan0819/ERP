<?php

use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingEventAccountMapping;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalEntryLine;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AccountingEventConvertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->markTestSkipped('AccountingEventConvertService is future Phase 4D-2B candidate and is not route-active yet.');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('module.accounting.events.convert', 'web');
    Permission::findOrCreate('module.accounting.journals.create', 'web');
});

function aeConvertServiceEnsureTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(['id' => $companyId], ['name' => 'AE Convert Service Company '.$companyId, 'code' => 'AECS'.$companyId, 'created_at' => now(), 'updated_at' => now()]);

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(['id' => $branchId], ['company_id' => $companyId, 'name' => 'AE Convert Service Branch '.$branchId, 'code' => 'AECSB'.$companyId.'-'.$branchId, 'created_at' => now(), 'updated_at' => now()]);
    }
}

function aeConvertServiceMakeUser(array $permissions = ['module.accounting.events.convert', 'module.accounting.journals.create'], int $companyId = 1, ?int $branchId = 10): User
{
    aeConvertServiceEnsureTenantRows($companyId, $branchId);

    $user = User::create([
        'name' => 'AE Convert Service User',
        'email' => 'ae-convert-service-'.uniqid().'@example.com',
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);

    $user->givePermissionTo($permissions);

    return $user;
}

function aeConvertServiceMakeEvent(User $user, array $overrides = []): AccountingEvent
{
    return AccountingEvent::create(array_merge([
        'company_id' => $user->company_id,
        'branch_id' => $user->branch_id,
        'source_type' => 'vehicle_sale_completion',
        'source_id' => 9301,
        'source_number' => 'SALE-AECS-001',
        'event_type' => 'vehicle_sale_completed',
        'event_date' => '2026-06-09',
        'status' => 'reviewed',
        'currency' => 'TWD',
        'amount' => 120000,
        // 技術註解：payload 故意含敏感與認列欄位，確認 convert audit 與 journal 生成不讀取這些資料。
        'payload' => ['vehicle_stock_number' => 'STK-AECS-001', 'customer_phone' => '0900000000', 'id_number' => 'A123456789', 'birthday' => '1990-01-01', 'address' => 'Hidden', 'profit' => 9, 'gross_profit' => 8, 'gross_margin' => 0.2, 'purchase_cost' => 1, 'cogs_amount' => 2, 'revenue_amount' => 3],
        'created_by' => $user->id,
        'reviewed_by' => $user->id,
        'reviewed_at' => now()->subHour()->setMicrosecond(0),
        'review_note' => 'Reviewed for service convert.',
    ], $overrides));
}

function aeConvertServiceMakeAccount(User $user, string $type, array $overrides = []): AccountingAccount
{
    return AccountingAccount::create(array_merge([
        'company_id' => $user->company_id,
        'branch_id' => null,
        'code' => 'AECS-'.strtoupper($type).'-'.uniqid(),
        'name' => 'AE Convert Service '.$type,
        'type' => $type,
        'opening_balance' => 0,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

function aeConvertServiceCreateMapping(AccountingEvent $event, string $key, AccountingAccount $account): AccountingEventAccountMapping
{
    return AccountingEventAccountMapping::create([
        'company_id' => $event->company_id,
        'branch_id' => null,
        'event_type' => $event->event_type,
        'source_type' => $event->source_type,
        'mapping_key' => $key,
        'account_id' => $account->id,
        'is_active' => true,
    ]);
}

function aeConvertServiceCreateRequiredMappings(AccountingEvent $event, AccountingAccount $receivable, AccountingAccount $revenue): void
{
    aeConvertServiceCreateMapping($event, 'accounts_receivable_account', $receivable);
    aeConvertServiceCreateMapping($event, 'sales_revenue_account', $revenue);
}

function aeConvertServiceConvert(AccountingEvent $event, User $user): AccountingJournalEntry
{
    return app(AccountingEventConvertService::class)->convert($event, $user);
}

function aeConvertServiceExpectValidationMessage(callable $callback, string $message): void
{
    try {
        $callback();
        expect(false)->toBeTrue('Expected validation exception was not thrown.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->all())->toContain($message);
    }
}

function aeConvertServiceAssertNoMutation(AccountingEvent $event, string $status = 'reviewed'): void
{
    $event->refresh();

    expect($event->status)->toBe($status)
        ->and($event->converted_journal_entry_id)->toBeNull()
        ->and(AccountingJournalEntry::count())->toBe(0)
        ->and(AccountingJournalEntryLine::count())->toBe(0);
}

it('converts reviewed event into draft journal and two revenue side lines', function (): void {
    $user = aeConvertServiceMakeUser();
    $receivable = aeConvertServiceMakeAccount($user, 'asset');
    $revenue = aeConvertServiceMakeAccount($user, 'revenue');
    $event = aeConvertServiceMakeEvent($user);
    $payload = $event->payload;
    $amount = $event->amount;
    $reviewedBy = $event->reviewed_by;
    $reviewedAt = $event->reviewed_at?->toDateTimeString();
    $reviewNote = $event->review_note;
    aeConvertServiceCreateRequiredMappings($event, $receivable, $revenue);

    $journal = aeConvertServiceConvert($event, $user);
    $event->refresh();
    $lines = $journal->lines;

    expect($journal->status)->toBe('draft')
        ->and($journal->source_type)->toBe('accounting_event')
        ->and($journal->source_id)->toBe($event->id)
        ->and($journal->journal_number)->toMatch('/^JE-202606-0001$/')
        ->and($lines)->toHaveCount(2)
        ->and((int) $lines[0]->account_id)->toBe($receivable->id)
        ->and($lines[0]->debit)->toBe('120000.00')
        ->and($lines[0]->credit)->toBe('0.00')
        ->and((int) $lines[1]->account_id)->toBe($revenue->id)
        ->and($lines[1]->debit)->toBe('0.00')
        ->and($lines[1]->credit)->toBe('120000.00')
        ->and($lines->sum(fn (AccountingJournalEntryLine $line): float => (float) $line->debit))->toBe($lines->sum(fn (AccountingJournalEntryLine $line): float => (float) $line->credit))
        ->and($event->status)->toBe('converted')
        ->and($event->converted_journal_entry_id)->toBe($journal->id)
        ->and($event->reviewed_by)->toBe($reviewedBy)
        ->and($event->reviewed_at?->toDateTimeString())->toBe($reviewedAt)
        ->and($event->review_note)->toBe($reviewNote)
        ->and($event->payload)->toBe($payload)
        ->and($event->amount)->toBe($amount);
});

it('writes safe converted audit without sensitive recognition keys', function (): void {
    $user = aeConvertServiceMakeUser();
    $event = aeConvertServiceMakeEvent($user);
    aeConvertServiceCreateRequiredMappings($event, aeConvertServiceMakeAccount($user, 'asset'), aeConvertServiceMakeAccount($user, 'revenue'));

    aeConvertServiceConvert($event, $user);

    $log = ActivityLog::query()->where('event', 'accounting_event.converted')->firstOrFail();
    $encoded = json_encode([$log->metadata, $log->old_values, $log->new_values], JSON_THROW_ON_ERROR);

    expect($log->description)->toBe('Accounting event converted')
        ->and($log->metadata)->toBe(['module' => 'accounting_events']);

    foreach (['payload', 'customer_phone', 'id_number', 'birthday', 'address', 'profit', 'gross_profit', 'gross_margin', 'purchase_cost', 'cogs_amount', 'revenue_amount'] as $forbiddenKey) {
        expect($encoded)->not->toContain($forbiddenKey);
    }
});

it('rejects second convert attempt without creating another journal', function (): void {
    $user = aeConvertServiceMakeUser();
    $event = aeConvertServiceMakeEvent($user);
    aeConvertServiceCreateRequiredMappings($event, aeConvertServiceMakeAccount($user, 'asset'), aeConvertServiceMakeAccount($user, 'revenue'));
    aeConvertServiceConvert($event, $user);

    aeConvertServiceExpectValidationMessage(fn () => aeConvertServiceConvert($event, $user), '此會計事件已產生傳票草稿。');

    expect(AccountingJournalEntry::count())->toBe(1)
        ->and(AccountingJournalEntryLine::count())->toBe(2);
});

it('missing database mapping rejects and does not mutate', function (): void {
    $user = aeConvertServiceMakeUser();
    $event = aeConvertServiceMakeEvent($user);

    aeConvertServiceExpectValidationMessage(fn () => aeConvertServiceConvert($event, $user), '會計事件映射尚未指定必要科目，無法產生傳票草稿。');
    aeConvertServiceAssertNoMutation($event);
});

it('user missing journal create permission rejects and does not mutate', function (): void {
    $user = aeConvertServiceMakeUser(['module.accounting.events.convert']);
    $event = aeConvertServiceMakeEvent($user);

    aeConvertServiceExpectValidationMessage(fn () => aeConvertServiceConvert($event, $user), '沒有建立會計傳票的權限。');
    aeConvertServiceAssertNoMutation($event);
});

it('pending voided and already converted events reject and do not mutate', function (string $case, string $status, int $expectedJournals): void {
    $user = aeConvertServiceMakeUser();
    $overrides = match ($case) {
        'pending' => ['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null],
        'voided' => ['status' => 'voided', 'voided_at' => now(), 'voided_by' => 1, 'void_reason' => 'voided'],
        'converted' => [
            'status' => 'converted',
            'converted_journal_entry_id' => AccountingJournalEntry::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'journal_number' => 'JE-AECS-EXISTING', 'entry_date' => '2026-06-09', 'summary' => 'Existing', 'status' => 'draft'])->id,
        ],
    };
    $event = aeConvertServiceMakeEvent($user, $overrides);

    aeConvertServiceExpectValidationMessage(fn () => aeConvertServiceConvert($event, $user), $status === 'converted' ? '此會計事件已產生傳票草稿。' : '只有已覆核的會計事件可以產生傳票草稿。');

    expect(AccountingJournalEntry::count())->toBe($expectedJournals);
    expect(AccountingJournalEntryLine::count())->toBe(0);
})->with([
    'pending' => ['pending', 'pending', 0],
    'voided' => ['voided', 'voided', 0],
    'converted' => ['converted', 'converted', 1],
]);

it('cross tenant event cannot be converted', function (): void {
    $user = aeConvertServiceMakeUser(['module.accounting.events.convert', 'module.accounting.journals.create'], 1, 10);
    $owner = aeConvertServiceMakeUser(['module.accounting.events.convert', 'module.accounting.journals.create'], 2, 20);
    $event = aeConvertServiceMakeEvent($owner);

    $this->expectException(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
    aeConvertServiceConvert($event, $user);
});

it('does not create cogs inventory tax overpayment refund or reversal lines', function (): void {
    $user = aeConvertServiceMakeUser();
    $event = aeConvertServiceMakeEvent($user);
    $receivable = aeConvertServiceMakeAccount($user, 'asset');
    $revenue = aeConvertServiceMakeAccount($user, 'revenue');
    aeConvertServiceCreateRequiredMappings($event, $receivable, $revenue);

    $journal = aeConvertServiceConvert($event, $user);
    $encoded = json_encode($journal->lines->toArray(), JSON_THROW_ON_ERROR);

    expect($journal->lines)->toHaveCount(2)
        ->and($journal->lines->pluck('account_id')->all())->toBe([$receivable->id, $revenue->id]);

    foreach (['vehicle_inventory_account', 'cogs_account', 'tax_payable_account', 'overpayment_account', 'refund', 'reversal'] as $forbiddenKey) {
        expect($encoded)->not->toContain($forbiddenKey);
    }
});
