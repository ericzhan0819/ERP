<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountingJournalEntryRequest;
use App\Http\Requests\UpdateAccountingJournalEntryRequest;
use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingJournalEntry;
use App\Services\AccountingJournalNumberService;
use App\Services\AccountingJournalValidator;
use App\Services\AuditLogService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountingJournalEntryController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly AccountingJournalNumberService $numberService,
        private readonly AccountingJournalValidator $journalValidator,
    ) {}

    /**
     * 技術註解：列表僅回傳畫面必要 allowlist 與每張傳票的借貸合計，避免暴露 tenant/actor 原始欄位與非本階段財務衍生資訊。
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AccountingJournalEntry::class);

        $statuses = config('accounting.journal_statuses', []);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(array_keys($statuses))],
        ]);

        $journals = $this->scopedJournalQuery($request->user())
            ->with(['creator:id,name', 'updater:id,name', 'lines:id,journal_entry_id,debit,credit'])
            ->when(trim((string) ($filters['q'] ?? '')) !== '', function (Builder $query) use ($filters): void {
                $q = trim((string) $filters['q']);
                $query->where(function (Builder $subQuery) use ($q): void {
                    $subQuery->where('journal_number', 'like', "%{$q}%")
                        ->orWhere('summary', 'like', "%{$q}%");
                });
            })
            ->when(($filters['status'] ?? '') !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(($filters['date_from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('entry_date', '>=', $filters['date_from']))
            ->when(($filters['date_to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('entry_date', '<=', $filters['date_to']))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AccountingJournalEntry $journal): array => $this->journalListPayload($journal));

        return Inertia::render('Accounting/JournalEntries/Index', [
            'journals' => $journals,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'journalStatuses' => $statuses,
            'can' => [
                'view' => $request->user()?->can('module.accounting.journals.view') ?? false,
                'create' => $request->user()?->can('module.accounting.journals.create') ?? false,
                'update' => $request->user()?->can('module.accounting.journals.update') ?? false,
                'post' => $request->user()?->can('module.accounting.journals.post') ?? false,
                'void' => $request->user()?->can('module.accounting.journals.void') ?? false,
            ],
        ]);
    }

    /**
     * 技術註解：建立頁僅提供啟用科目清單與預設日期，不提早串接 AR/AP/成本資料，避免超出本階段範圍。
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', AccountingJournalEntry::class);

        return Inertia::render('Accounting/JournalEntries/Create', [
            'accounts' => $this->activeAccountsPayload($request->user()),
            'defaults' => [
                'entry_date' => now()->toDateString(),
            ],
            'journalStatuses' => config('accounting.journal_statuses', []),
            'can' => [
                'create' => $request->user()?->can('module.accounting.journals.create') ?? false,
                'update' => $request->user()?->can('module.accounting.journals.update') ?? false,
            ],
        ]);
    }

    /**
     * 技術註解：建立草稿以 transaction 包裝 header + lines，並由後端產生 journal number，阻擋前端偽造編號與 tenant 欄位。
     */
    public function store(StoreAccountingJournalEntryRequest $request)
    {
        $this->authorize('create', AccountingJournalEntry::class);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $validated = $request->validated();

        $this->journalValidator->validateDraftLines($validated['lines'], (int) $user->company_id);
        $normalizedLines = $this->journalValidator->normalizeLines($validated['lines']);

        $journal = DB::transaction(function () use ($user, $validated, $normalizedLines): AccountingJournalEntry {
            $journal = AccountingJournalEntry::create([
                'company_id' => (int) $user->company_id,
                'branch_id' => $user->branch_id !== null ? (int) $user->branch_id : null,
                'journal_number' => $this->numberService->generate((int) $user->company_id, new \DateTimeImmutable($validated['entry_date'])),
                'entry_date' => $validated['entry_date'],
                'summary' => $validated['summary'] ?? null,
                'status' => 'draft',
                'created_by' => (int) $user->id,
                'updated_by' => (int) $user->id,
            ]);

            $journal->lines()->createMany($normalizedLines->all());

            return $journal->load(['lines.account', 'creator:id,name', 'updater:id,name']);
        });

        $this->auditLogService->log(
            actor: $user,
            action: 'accounting_journal.created',
            description: '新增會計傳票',
            metadata: ['module' => 'accounting_journals'],
            subject: $journal,
            newValues: $this->auditPayload($journal),
            request: $request,
            event: 'accounting_journal.created',
        );

        return redirect()->route('employee-system.accounting.journal-entries.show', $journal->id);
    }

    /**
     * 技術註解：明細頁只回傳 header + lines allowlist，不輸出 company_id / branch_id / actor id 等敏感欄位。
     */
    public function show(Request $request, int $journalEntry): Response
    {
        $journal = $this->scopedJournalDetailQuery($request->user())->whereKey($journalEntry)->firstOrFail();
        $this->authorize('view', $journal);

        return Inertia::render('Accounting/JournalEntries/Show', [
            'journal' => $this->journalDetailPayload($journal, $request->user()),
            'journalStatuses' => config('accounting.journal_statuses', []),
            'can' => [
                'view' => $request->user()?->can('module.accounting.journals.view') ?? false,
                'update' => $request->user()?->can('module.accounting.journals.update') ?? false,
                'post' => $request->user()?->can('module.accounting.journals.post') ?? false,
                'void' => $request->user()?->can('module.accounting.journals.void') ?? false,
            ],
        ]);
    }

    /**
     * 技術註解：編輯僅允許 draft，先 scoped 查詢後授權，避免跨租戶或已鎖定狀態傳票被探測。
     */
    public function edit(Request $request, int $journalEntry): Response
    {
        $journal = $this->scopedJournalDetailQuery($request->user())->whereKey($journalEntry)->firstOrFail();
        $this->authorize('update', $journal);

        return Inertia::render('Accounting/JournalEntries/Edit', [
            'journal' => $this->journalEditPayload($journal),
            'accounts' => $this->activeAccountsPayload($request->user()),
            'journalStatuses' => config('accounting.journal_statuses', []),
            'can' => [
                'update' => $request->user()?->can('module.accounting.journals.update') ?? false,
            ],
        ]);
    }

    /**
     * 技術註解：更新 draft 以刪除舊 lines 後重建方式同步，可避免複雜差異同步導致殘留舊分錄或排序錯亂。
     */
    public function update(UpdateAccountingJournalEntryRequest $request, int $journalEntry)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $journal = $this->scopedJournalDetailQuery($user)->whereKey($journalEntry)->firstOrFail();
        $this->authorize('update', $journal);

        $validated = $request->validated();
        $this->journalValidator->validateDraftLines($validated['lines'], (int) $user->company_id);
        $normalizedLines = $this->journalValidator->normalizeLines($validated['lines']);
        $oldValues = $this->auditPayload($journal);

        DB::transaction(function () use ($journal, $user, $validated, $normalizedLines): void {
            $journal->update([
                'entry_date' => $validated['entry_date'],
                'summary' => $validated['summary'] ?? null,
                'updated_by' => (int) $user->id,
            ]);

            $journal->lines()->delete();
            $journal->lines()->createMany($normalizedLines->all());
        });

        $freshJournal = $this->scopedJournalDetailQuery($user)->whereKey($journal->id)->firstOrFail();
        $newValues = $this->auditPayload($freshJournal);

        $changedOldValues = [];
        $changedNewValues = [];

        foreach ($oldValues as $field => $oldValue) {
            if (($newValues[$field] ?? null) !== $oldValue) {
                $changedOldValues[$field] = $oldValue;
                $changedNewValues[$field] = $newValues[$field] ?? null;
            }
        }

        if ($changedNewValues !== []) {
            $this->auditLogService->log(
                actor: $user,
                action: 'accounting_journal.updated',
                description: '更新會計傳票',
                metadata: ['module' => 'accounting_journals'],
                subject: $freshJournal,
                oldValues: $changedOldValues,
                newValues: $changedNewValues,
                request: $request,
                event: 'accounting_journal.updated',
            );
        }

        return redirect()->route('employee-system.accounting.journal-entries.show', $freshJournal->id);
    }

    /**
     * 技術註解：過帳僅允許 draft，並於後端重新驗證借貸平衡與最少兩列，避免前端狀態或舊畫面繞過財務鎖定規則。
     */
    public function post(Request $request, int $journalEntry)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $journal = $this->scopedJournalDetailQuery($user)->whereKey($journalEntry)->firstOrFail();

        if (! $user->can('module.accounting.journals.post')) {
            abort(403);
        }

        if ($journal->status !== 'draft') {
            throw ValidationException::withMessages(['status' => '只有草稿傳票可以過帳。']);
        }

        $lines = $journal->lines->map(fn ($line): array => [
            'account_id' => (int) $line->account_id,
            'debit' => (float) $line->debit,
            'credit' => (float) $line->credit,
            'memo' => $line->memo,
            'sort_order' => (int) $line->sort_order,
        ])->all();

        $this->journalValidator->validateDraftLines($lines, (int) $user->company_id);
        $oldValues = $this->auditPayload($journal);

        $journal->update([
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ]);

        $freshJournal = $this->scopedJournalDetailQuery($user)->whereKey($journal->id)->firstOrFail();

        $this->auditLogService->log(
            actor: $user,
            action: 'accounting_journal.posted',
            description: '會計傳票已過帳',
            metadata: ['module' => 'accounting_journals'],
            subject: $freshJournal,
            oldValues: $oldValues,
            newValues: $this->auditPayload($freshJournal),
            request: $request,
            event: 'accounting_journal.posted',
        );

        return redirect()->route('employee-system.accounting.journal-entries.show', $freshJournal->id);
    }

    /**
     * 技術註解：作廢僅允許 posted 且必填原因，保留原始分錄並以狀態鎖定，避免直接刪除破壞稽核軌跡。
     */
    public function void(Request $request, int $journalEntry)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $journal = $this->scopedJournalDetailQuery($user)->whereKey($journalEntry)->firstOrFail();

        if (! $user->can('module.accounting.journals.void')) {
            abort(403);
        }

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($journal->status !== 'posted') {
            throw ValidationException::withMessages(['status' => '只有已過帳傳票可以作廢。']);
        }

        $oldValues = $this->auditPayload($journal);

        $journal->update([
            'status' => 'voided',
            'voided_at' => now(),
            'voided_by' => (int) $user->id,
            'void_reason' => $validated['void_reason'],
            'updated_by' => (int) $user->id,
        ]);

        $freshJournal = $this->scopedJournalDetailQuery($user)->whereKey($journal->id)->firstOrFail();

        $this->auditLogService->log(
            actor: $user,
            action: 'accounting_journal.voided',
            description: '會計傳票已作廢',
            metadata: ['module' => 'accounting_journals'],
            subject: $freshJournal,
            oldValues: $oldValues,
            newValues: $this->auditPayload($freshJournal),
            request: $request,
            event: 'accounting_journal.voided',
        );

        return redirect()->route('employee-system.accounting.journal-entries.show', $freshJournal->id);
    }

    private function scopedJournalQuery(?Authenticatable $user): Builder
    {
        $userCompanyId = (int) ($user?->company_id ?? 0);
        $userBranchId = $user?->branch_id;

        return AccountingJournalEntry::query()
            ->where('company_id', $userCompanyId)
            ->when($userBranchId !== null, fn (Builder $query) => $query->where(function (Builder $branchQuery) use ($userBranchId): void {
                $branchQuery->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $userBranchId);
            }));
    }

    private function scopedJournalDetailQuery(?Authenticatable $user): Builder
    {
        return $this->scopedJournalQuery($user)
            ->with([
                'creator:id,name',
                'updater:id,name',
                'lines' => fn ($query) => $query->with('account:id,code,name,type,is_active')->orderBy('sort_order')->orderBy('id'),
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeAccountsPayload(?Authenticatable $user): array
    {
        /** @var \App\Models\User $user */
        $companyId = (int) ($user?->company_id ?? 0);
        $branchId = $user?->branch_id;

        return AccountingAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($branchId !== null, fn (Builder $query) => $query->where(function (Builder $branchQuery) use ($branchId): void {
                $branchQuery->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $branchId);
            }))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (AccountingAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ])
            ->all();
    }

    private function journalListPayload(AccountingJournalEntry $journal): array
    {
        $totalDebit = round((float) $journal->lines->sum(fn ($line) => (float) $line->debit), 2);
        $totalCredit = round((float) $journal->lines->sum(fn ($line) => (float) $line->credit), 2);

        return [
            'id' => $journal->id,
            'journal_number' => $journal->journal_number,
            'entry_date' => optional($journal->entry_date)->toDateString(),
            'summary' => $journal->summary,
            'status' => $journal->status,
            'posted_at' => optional($journal->posted_at)->toISOString(),
            'voided_at' => optional($journal->voided_at)->toISOString(),
            'void_reason' => $journal->void_reason,
            'total_debit' => number_format($totalDebit, 2, '.', ''),
            'total_credit' => number_format($totalCredit, 2, '.', ''),
            'operator_name' => $journal->updater?->name ?? $journal->creator?->name,
            'updated_at' => optional($journal->updated_at)->toISOString(),
        ];
    }

    private function journalDetailPayload(AccountingJournalEntry $journal, ?Authenticatable $user): array
    {
        return [
            'id' => $journal->id,
            'journal_number' => $journal->journal_number,
            'entry_date' => optional($journal->entry_date)->toDateString(),
            'summary' => $journal->summary,
            'status' => $journal->status,
            'source_type' => $journal->source_type,
            'source_id' => $journal->source_id,
            'source_accounting_event' => $this->sourceAccountingEventPayload($journal, $user),
            'posted_at' => optional($journal->posted_at)->toISOString(),
            'voided_at' => optional($journal->voided_at)->toISOString(),
            'void_reason' => $journal->void_reason,
            'total_debit' => number_format((float) $journal->lines->sum(fn ($line) => (float) $line->debit), 2, '.', ''),
            'total_credit' => number_format((float) $journal->lines->sum(fn ($line) => (float) $line->credit), 2, '.', ''),
            'operator_name' => $journal->updater?->name ?? $journal->creator?->name,
            'lines' => $journal->lines->map(fn ($line): array => [
                'id' => $line->id,
                'account' => [
                    'id' => $line->account?->id,
                    'code' => $line->account?->code,
                    'name' => $line->account?->name,
                    'type' => $line->account?->type,
                ],
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'memo' => $line->memo,
                'sort_order' => $line->sort_order,
            ])->all(),
        ];
    }

    /**
     * 技術註解：來源事件連結必須再次檢查 events.view 與 tenant 範圍，避免 journal 檢視者藉 source_id 推測跨租戶或敏感會計事件內容。
     *
     * @return array<string, mixed>|null
     */
    private function sourceAccountingEventPayload(AccountingJournalEntry $journal, ?Authenticatable $user): ?array
    {
        if ($journal->source_type !== 'accounting_event' || $journal->source_id === null || ! $user?->can('module.accounting.events.view')) {
            return null;
        }

        $event = AccountingEvent::query()
            ->where('company_id', (int) ($user->company_id ?? 0))
            ->when($user->branch_id !== null, fn (Builder $query) => $query->where(function (Builder $branchQuery) use ($user): void {
                $branchQuery->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $user->branch_id);
            }))
            ->whereKey((int) $journal->source_id)
            ->first();

        if (! $event) {
            return null;
        }

        $eventTypes = config('accounting_events.event_types', []);
        $statuses = config('accounting_events.statuses', []);

        return [
            'id' => $event->id,
            'source_number' => $event->source_number,
            'event_type' => $event->event_type,
            'event_type_label' => $eventTypes[$event->event_type] ?? $event->event_type,
            'status' => $event->status,
            'status_label' => $statuses[$event->status] ?? $event->status,
            'amount' => (string) $event->amount,
            'currency' => $event->currency,
            'event_date' => optional($event->event_date)->toDateString(),
        ];
    }

    private function journalEditPayload(AccountingJournalEntry $journal): array
    {
        return [
            'id' => $journal->id,
            'journal_number' => $journal->journal_number,
            'entry_date' => optional($journal->entry_date)->toDateString(),
            'summary' => $journal->summary,
            'status' => $journal->status,
            'posted_at' => optional($journal->posted_at)->toISOString(),
            'voided_at' => optional($journal->voided_at)->toISOString(),
            'void_reason' => $journal->void_reason,
            'lines' => $journal->lines->map(fn ($line): array => [
                'account_id' => $line->account_id,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'memo' => $line->memo,
                'sort_order' => $line->sort_order,
            ])->all(),
        ];
    }

    private function auditPayload(AccountingJournalEntry $journal): array
    {
        $totalDebit = round((float) $journal->lines()->sum('debit'), 2);
        $totalCredit = round((float) $journal->lines()->sum('credit'), 2);

        return [
            'journal_number' => $journal->journal_number,
            'entry_date' => optional($journal->entry_date)->toDateString(),
            'summary' => $journal->summary,
            'status' => $journal->status,
            'posted_at' => optional($journal->posted_at)->toISOString(),
            'posted_by' => $journal->posted_by,
            'voided_at' => optional($journal->voided_at)->toISOString(),
            'voided_by' => $journal->voided_by,
            'void_reason' => $journal->void_reason,
            'total_debit' => number_format($totalDebit, 2, '.', ''),
            'total_credit' => number_format($totalCredit, 2, '.', ''),
        ];
    }
}
