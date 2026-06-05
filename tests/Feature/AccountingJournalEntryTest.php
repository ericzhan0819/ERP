<?php

use App\Models\AccountingAccount;
use App\Models\AccountingJournalEntry;
use App\Models\ActivityLog;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
});

function ensureJournalTenantRows(int $companyId, ?int $branchId): void
{
    DB::table('companies')->updateOrInsert(
        ['id' => $companyId],
        ['name' => 'Journal Company '.$companyId, 'code' => 'JC'.$companyId, 'created_at' => now(), 'updated_at' => now()]
    );

    if ($branchId !== null) {
        DB::table('branches')->updateOrInsert(
            ['id' => $branchId],
            ['company_id' => $companyId, 'name' => 'Journal Branch '.$branchId, 'code' => 'JB'.$branchId, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}

function makeJournalUser(string $email, int $companyId = 1, ?int $branchId = 10): User
{
    ensureJournalTenantRows($companyId, $branchId);

    return User::create([
        'name' => 'Journal User',
        'email' => $email,
        'password' => 'password',
        'account_status' => 'active',
        'is_active' => true,
        'company_id' => $companyId,
        'branch_id' => $branchId,
    ]);
}

function makeJournalAccount(User $user, array $overrides = []): AccountingAccount
{
    static $seq = 1000;
    $seq++;

    return AccountingAccount::create(array_merge([
        'company_id' => (int) $user->company_id,
        'branch_id' => $user->branch_id,
        'code' => (string) $seq,
        'name' => '傳票科目 '.$seq,
        'type' => 'asset',
        'opening_balance' => 0,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $overrides));
}

function validJournalPayload(AccountingAccount $debitAccount, AccountingAccount $creditAccount, array $overrides = []): array
{
    return array_merge([
        'entry_date' => '2026-06-05',
        'summary' => '手動建立草稿傳票',
        'lines' => [
            ['account_id' => $debitAccount->id, 'debit' => 1000, 'credit' => 0, 'memo' => '借方測試', 'sort_order' => 0],
            ['account_id' => $creditAccount->id, 'debit' => 0, 'credit' => 1000, 'memo' => '貸方測試', 'sort_order' => 1],
        ],
    ], $overrides);
}

it('有 journals.view 可進 index', function (): void {
    $user = makeJournalUser('journal-index-allow@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view');

    $this->actingAs($user)
        ->get(route('employee-system.accounting.journal-entries.index'))
        ->assertOk();
});

it('無 journals.view 403', function (): void {
    $user = makeJournalUser('journal-index-deny@example.com');
    $user->givePermissionTo('module.accounting.view');

    $this->actingAs($user)
        ->get(route('employee-system.accounting.journal-entries.index'))
        ->assertForbidden();
});

it('create 頁需要 journals.create', function (): void {
    $user = makeJournalUser('journal-create-deny@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view');

    $this->actingAs($user)
        ->get(route('employee-system.accounting.journal-entries.create'))
        ->assertForbidden();
});

it('store balanced draft journal 成功', function (): void {
    $user = makeJournalUser('journal-store@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create');
    $debitAccount = makeJournalAccount($user, ['code' => '1101']);
    $creditAccount = makeJournalAccount($user, ['code' => '2101', 'type' => 'liability']);

    $this->actingAs($user)
        ->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount))
        ->assertRedirect();

    $journal = AccountingJournalEntry::query()->firstOrFail();

    expect($journal->status)->toBe('draft')
        ->and($journal->journal_number)->toBe('JE-202606-0001')
        ->and($journal->lines()->count())->toBe(2);
});

it('unbalanced journal 建立失敗', function (): void {
    $user = makeJournalUser('journal-unbalanced@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.create');
    $debitAccount = makeJournalAccount($user);
    $creditAccount = makeJournalAccount($user, ['type' => 'liability']);

    $this->actingAs($user)
        ->from(route('employee-system.accounting.journal-entries.create'))
        ->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount, [
            'lines' => [
                ['account_id' => $debitAccount->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $creditAccount->id, 'debit' => 0, 'credit' => 900],
            ],
        ]))
        ->assertSessionHasErrors('lines');
});

it('journal 至少兩列', function (): void {
    $user = makeJournalUser('journal-min-lines@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.create');
    $debitAccount = makeJournalAccount($user);
    $creditAccount = makeJournalAccount($user, ['type' => 'liability']);

    $this->actingAs($user)
        ->from(route('employee-system.accounting.journal-entries.create'))
        ->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount, [
            'lines' => [
                ['account_id' => $debitAccount->id, 'debit' => 1000, 'credit' => 0],
            ],
        ]))
        ->assertSessionHasErrors('lines');
});

it('單列不可 debit credit 同時大於 0', function (): void {
    $user = makeJournalUser('journal-line-both-positive@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.create');
    $debitAccount = makeJournalAccount($user);
    $creditAccount = makeJournalAccount($user, ['type' => 'liability']);

    $this->actingAs($user)
        ->from(route('employee-system.accounting.journal-entries.create'))
        ->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount, [
            'lines' => [
                ['account_id' => $debitAccount->id, 'debit' => 500, 'credit' => 500],
                ['account_id' => $creditAccount->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]))
        ->assertSessionHasErrors('lines.0');
});

it('單列不可 debit credit 同時為 0', function (): void {
    $user = makeJournalUser('journal-line-both-zero@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.create');
    $debitAccount = makeJournalAccount($user);
    $creditAccount = makeJournalAccount($user, ['type' => 'liability']);

    $this->actingAs($user)
        ->from(route('employee-system.accounting.journal-entries.create'))
        ->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount, [
            'lines' => [
                ['account_id' => $debitAccount->id, 'debit' => 0, 'credit' => 0],
                ['account_id' => $creditAccount->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]))
        ->assertSessionHasErrors('lines.0');
});

it('draft journal 可 update', function (): void {
    $user = makeJournalUser('journal-update@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.update');
    $debitAccount = makeJournalAccount($user, ['code' => '1201']);
    $creditAccount = makeJournalAccount($user, ['code' => '2201', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.update', $journal->id), validJournalPayload($debitAccount, $creditAccount, [
            'summary' => '更新後草稿',
        ]))
        ->assertRedirect(route('employee-system.accounting.journal-entries.show', $journal->id));

    expect($journal->fresh()->summary)->toBe('更新後草稿');
});

it('draft 可 post', function (): void {
    $user = makeJournalUser('journal-post@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.post');
    $debitAccount = makeJournalAccount($user, ['code' => '1301']);
    $creditAccount = makeJournalAccount($user, ['code' => '2301', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.post', $journal->id))
        ->assertRedirect(route('employee-system.accounting.journal-entries.show', $journal->id));

    $journal->refresh();

    expect($journal->status)->toBe('posted')
        ->and($journal->posted_at)->not->toBeNull()
        ->and($journal->posted_by)->toBe($user->id);
});

it('unbalanced draft 不可 post', function (): void {
    $user = makeJournalUser('journal-post-unbalanced@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.post');
    $debitAccount = makeJournalAccount($user, ['code' => '1302']);
    $creditAccount = makeJournalAccount($user, ['code' => '2302', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $journal->lines()->where('credit', '>', 0)->firstOrFail()->update(['credit' => 900]);

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.post', $journal->id))
        ->assertSessionHasErrors('lines');

    expect($journal->fresh()->status)->toBe('draft');
});

it('posted 不可 update', function (): void {
    $user = makeJournalUser('journal-posted-no-update@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.update', 'module.accounting.journals.post');
    $debitAccount = makeJournalAccount($user, ['code' => '1303']);
    $creditAccount = makeJournalAccount($user, ['code' => '2303', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.post', $journal->id));

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.update', $journal->id), validJournalPayload($debitAccount, $creditAccount, ['summary' => '不可更新']))
        ->assertForbidden();
});

it('posted 可 void', function (): void {
    $user = makeJournalUser('journal-void@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.post', 'module.accounting.journals.void');
    $debitAccount = makeJournalAccount($user, ['code' => '1304']);
    $creditAccount = makeJournalAccount($user, ['code' => '2304', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.post', $journal->id));

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.void', $journal->id), ['void_reason' => '測試作廢'])
        ->assertRedirect(route('employee-system.accounting.journal-entries.show', $journal->id));

    $journal->refresh();

    expect($journal->status)->toBe('voided')
        ->and($journal->voided_at)->not->toBeNull()
        ->and($journal->voided_by)->toBe($user->id)
        ->and($journal->void_reason)->toBe('測試作廢');
});

it('void 需要 void_reason', function (): void {
    $user = makeJournalUser('journal-void-reason@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.post', 'module.accounting.journals.void');
    $debitAccount = makeJournalAccount($user, ['code' => '1305']);
    $creditAccount = makeJournalAccount($user, ['code' => '2305', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.post', $journal->id));

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.void', $journal->id), ['void_reason' => ''])
        ->assertSessionHasErrors('void_reason');
});

it('voided 不可 update', function (): void {
    $user = makeJournalUser('journal-voided-no-update@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.update', 'module.accounting.journals.post', 'module.accounting.journals.void');
    $debitAccount = makeJournalAccount($user, ['code' => '1306']);
    $creditAccount = makeJournalAccount($user, ['code' => '2306', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.post', $journal->id));
    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.void', $journal->id), ['void_reason' => '測試']);

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.update', $journal->id), validJournalPayload($debitAccount, $creditAccount, ['summary' => '不可更新']))
        ->assertForbidden();
});

it('voided 不可 post', function (): void {
    $user = makeJournalUser('journal-voided-no-post@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.post', 'module.accounting.journals.void');
    $debitAccount = makeJournalAccount($user, ['code' => '1307']);
    $creditAccount = makeJournalAccount($user, ['code' => '2307', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.post', $journal->id));
    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.void', $journal->id), ['void_reason' => '測試']);

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.post', $journal->id))
        ->assertSessionHasErrors('status');
});

it('draft 不可 void', function (): void {
    $user = makeJournalUser('journal-draft-no-void@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.void');
    $debitAccount = makeJournalAccount($user, ['code' => '1308']);
    $creditAccount = makeJournalAccount($user, ['code' => '2308', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.void', $journal->id), ['void_reason' => '測試'])
        ->assertSessionHasErrors('status');
});

it('無 post 權限不可 post', function (): void {
    $user = makeJournalUser('journal-no-post-permission@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create');
    $debitAccount = makeJournalAccount($user, ['code' => '1309']);
    $creditAccount = makeJournalAccount($user, ['code' => '2309', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.post', $journal->id))
        ->assertForbidden();
});

it('無 void 權限不可 void', function (): void {
    $user = makeJournalUser('journal-no-void-permission@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.post');
    $debitAccount = makeJournalAccount($user, ['code' => '1310']);
    $creditAccount = makeJournalAccount($user, ['code' => '2310', 'type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();
    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.post', $journal->id));

    $this->actingAs($user)
        ->patch(route('employee-system.accounting.journal-entries.void', $journal->id), ['void_reason' => '測試'])
        ->assertForbidden();
});

it('跨 tenant 不可 post void', function (): void {
    $owner = makeJournalUser('journal-cross-tenant-owner@example.com', 1, 10);
    $other = makeJournalUser('journal-cross-tenant-other@example.com', 2, 20);
    $owner->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create');
    $other->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.post', 'module.accounting.journals.void');
    $debitAccount = makeJournalAccount($owner, ['code' => '1311']);
    $creditAccount = makeJournalAccount($owner, ['code' => '2311', 'type' => 'liability']);

    $this->actingAs($owner)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($other)
        ->patch(route('employee-system.accounting.journal-entries.post', $journal->id))
        ->assertNotFound();

    $this->actingAs($other)
        ->patch(route('employee-system.accounting.journal-entries.void', $journal->id), ['void_reason' => '測試'])
        ->assertNotFound();
});

it('journal_number 自動產生 JE-YYYYMM-0001', function (): void {
    $user = makeJournalUser('journal-number-format@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.create');
    $debitAccount = makeJournalAccount($user);
    $creditAccount = makeJournalAccount($user, ['type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));

    expect(AccountingJournalEntry::query()->value('journal_number'))->toBe('JE-202606-0001');
});

it('journal lines account 必須同 company', function (): void {
    $user = makeJournalUser('journal-cross-company-account@example.com', 1, 10);
    $otherUser = makeJournalUser('journal-cross-company-owner@example.com', 2, 20);
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.create');
    $debitAccount = makeJournalAccount($user);
    $foreignAccount = makeJournalAccount($otherUser, ['type' => 'liability']);

    $this->actingAs($user)
        ->from(route('employee-system.accounting.journal-entries.create'))
        ->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $foreignAccount))
        ->assertSessionHasErrors('lines');
});

it('跨 tenant journal 不可 show edit update', function (): void {
    $owner = makeJournalUser('journal-owner@example.com', 1, 10);
    $other = makeJournalUser('journal-other@example.com', 2, 20);
    $owner->givePermissionTo('module.accounting.view', 'module.accounting.journals.create', 'module.accounting.journals.view');
    $other->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.update');
    $debitAccount = makeJournalAccount($owner);
    $creditAccount = makeJournalAccount($owner, ['type' => 'liability']);

    $this->actingAs($owner)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($other)->get(route('employee-system.accounting.journal-entries.show', $journal->id))->assertNotFound();
    $this->actingAs($other)->get(route('employee-system.accounting.journal-entries.edit', $journal->id))->assertNotFound();
    $this->actingAs($other)->patch(route('employee-system.accounting.journal-entries.update', $journal->id), validJournalPayload($debitAccount, $creditAccount))->assertNotFound();
});

it('payload 不包含 company_id branch_id created_by updated_by', function (): void {
    $user = makeJournalUser('journal-payload-private@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create');
    $debitAccount = makeJournalAccount($user);
    $creditAccount = makeJournalAccount($user, ['type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));

    $this->actingAs($user)
        ->get(route('employee-system.accounting.journal-entries.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('journals.data.0.company_id')
            ->missing('journals.data.0.branch_id')
            ->missing('journals.data.0.created_by')
            ->missing('journals.data.0.updated_by')
        );
});

it('payload 不包含 profit gross_margin margin net_profit', function (): void {
    $user = makeJournalUser('journal-payload-no-profit@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create');
    $debitAccount = makeJournalAccount($user);
    $creditAccount = makeJournalAccount($user, ['type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)
        ->get(route('employee-system.accounting.journal-entries.show', $journal->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('journal.profit')
            ->missing('journal.gross_margin')
            ->missing('journal.margin')
            ->missing('journal.net_profit')
        );
});

it('audit accounting_journal created updated posted voided', function (): void {
    $user = makeJournalUser('journal-audit@example.com');
    $user->givePermissionTo('module.accounting.view', 'module.accounting.journals.view', 'module.accounting.journals.create', 'module.accounting.journals.update', 'module.accounting.journals.post', 'module.accounting.journals.void');
    $debitAccount = makeJournalAccount($user);
    $creditAccount = makeJournalAccount($user, ['type' => 'liability']);

    $this->actingAs($user)->post(route('employee-system.accounting.journal-entries.store'), validJournalPayload($debitAccount, $creditAccount));
    $journal = AccountingJournalEntry::query()->firstOrFail();

    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.update', $journal->id), validJournalPayload($debitAccount, $creditAccount, ['summary' => 'Audit Update']));
    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.post', $journal->id));
    $this->actingAs($user)->patch(route('employee-system.accounting.journal-entries.void', $journal->id), ['void_reason' => 'Audit Void']);

    expect(ActivityLog::query()->where('event', 'accounting_journal.created')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'accounting_journal.updated')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'accounting_journal.posted')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'accounting_journal.voided')->exists())->toBeTrue();
});

it('accounting role 有 journals view create update post void', function (): void {
    $role = Role::findByName('accounting', 'web');

    expect($role->hasPermissionTo('module.accounting.journals.view'))->toBeTrue()
        ->and($role->hasPermissionTo('module.accounting.journals.create'))->toBeTrue()
        ->and($role->hasPermissionTo('module.accounting.journals.update'))->toBeTrue()
        ->and($role->hasPermissionTo('module.accounting.journals.post'))->toBeTrue()
        ->and($role->hasPermissionTo('module.accounting.journals.void'))->toBeTrue();
});

it('sales inventory viewer 不預設 journals 權限', function (): void {
    foreach (['sales', 'inventory', 'viewer'] as $roleName) {
        $role = Role::findByName($roleName, 'web');
        expect($role->hasPermissionTo('module.accounting.journals.view'))->toBeFalse();
    }
});

it('module registry accounting route 仍維持 accounts 入口且可涵蓋 journals active pattern', function (): void {
    $module = Module::query()->where('key', 'accounting')->firstOrFail();

    expect($module->route_name)->toBe('employee-system.accounting.accounts.index')
        ->and($module->base_permission)->toBe('module.accounting.view')
        ->and($module->active_patterns)->toContain('employee-system.accounting.journal-entries.*');
});