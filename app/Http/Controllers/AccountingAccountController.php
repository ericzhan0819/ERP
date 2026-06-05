<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountingAccountRequest;
use App\Http\Requests\UpdateAccountingAccountRequest;
use App\Models\AccountingAccount;
use App\Services\AuditLogService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountingAccountController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * 技術註解：列表僅輸出會計科目畫面所需 allowlist，不回傳 tenant/actor 原始欄位，以降低資料邊界與敏感欄位外洩風險。
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AccountingAccount::class);

        $accountTypes = config('accounting.account_types', []);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', Rule::in(array_keys($accountTypes))],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        $accounts = $this->scopedAccountQuery($request->user())
            ->with(['creator:id,name', 'updater:id,name'])
            ->when(trim((string) ($filters['q'] ?? '')) !== '', function (Builder $query) use ($filters): void {
                $q = trim((string) $filters['q']);
                $query->where(function (Builder $subQuery) use ($q): void {
                    $subQuery->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->when(($filters['type'] ?? '') !== '', fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', $filters['is_active'] === '1'))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AccountingAccount $account): array => $this->accountListPayload($account));

        return Inertia::render('Accounting/Accounts/Index', [
            'accounts' => $accounts,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'type' => $filters['type'] ?? '',
                'is_active' => array_key_exists('is_active', $filters) ? (string) ($filters['is_active'] ?? '') : '',
            ],
            'accountTypes' => $accountTypes,
            'can' => [
                'create' => $request->user()?->can('module.accounting.accounts.create') ?? false,
                'update' => $request->user()?->can('module.accounting.accounts.update') ?? false,
            ],
        ]);
    }

    /**
     * 技術註解：建立頁只提供最小字典與權限旗標，不預載其他會計模組資料，避免超出 Phase 1 範圍。
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', AccountingAccount::class);

        return Inertia::render('Accounting/Accounts/Create', [
            'accountTypes' => config('accounting.account_types', []),
            'can' => [
                'create' => $request->user()?->can('module.accounting.accounts.create') ?? false,
                'update' => $request->user()?->can('module.accounting.accounts.update') ?? false,
            ],
        ]);
    }

    /**
     * 技術註解：建立時 company/branch/actor 一律由後端寫入，阻止前端以 payload 覆寫 tenant 邊界或偽造操作者。
     */
    public function store(StoreAccountingAccountRequest $request)
    {
        $this->authorize('create', AccountingAccount::class);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $validated = $request->validated();

        $request->validate([
            'code' => [
                'required',
                Rule::unique('accounting_accounts', 'code')->where(fn ($query) => $query->where('company_id', (int) $user->company_id)),
            ],
        ]);

        $account = AccountingAccount::create([
            'company_id' => (int) $user->company_id,
            'branch_id' => $user->branch_id !== null ? (int) $user->branch_id : null,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'opening_balance' => $validated['opening_balance'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
            'created_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ]);

        $this->auditLogService->log(
            actor: $user,
            action: 'accounting_account.created',
            description: '新增會計科目',
            metadata: ['module' => 'accounting_accounts'],
            subject: $account,
            newValues: $this->auditPayload($account),
            request: $request,
            event: 'accounting_account.created',
        );

        return redirect()->route('employee-system.accounting.accounts.index');
    }

    /**
     * 技術註解：編輯頁先 tenant scoped 查詢再授權，若跨 tenant 一律 404 優先，避免被用於科目存在性探測。
     */
    public function edit(Request $request, int $account): Response
    {
        $foundAccount = $this->scopedAccountQuery($request->user())->whereKey($account)->firstOrFail();
        $this->authorize('update', $foundAccount);

        return Inertia::render('Accounting/Accounts/Edit', [
            'account' => $this->accountEditPayload($foundAccount),
            'accountTypes' => config('accounting.account_types', []),
            'can' => [
                'create' => $request->user()?->can('module.accounting.accounts.create') ?? false,
                'update' => $request->user()?->can('module.accounting.accounts.update') ?? false,
            ],
        ]);
    }

    /**
     * 技術註解：更新採 validated allowlist 與 company unique code 檢查，不接受 tenant/actor 欄位覆寫，避免 mass assignment 與 privilege escalation。
     */
    public function update(UpdateAccountingAccountRequest $request, int $account)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $foundAccount = $this->scopedAccountQuery($user)->whereKey($account)->firstOrFail();
        $this->authorize('update', $foundAccount);

        $validated = $request->validated();

        $request->validate([
            'code' => [
                'required',
                Rule::unique('accounting_accounts', 'code')
                    ->ignore($foundAccount->id)
                    ->where(fn ($query) => $query->where('company_id', (int) $user->company_id)),
            ],
        ]);

        $oldValues = $this->auditPayload($foundAccount);

        $foundAccount->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'opening_balance' => $validated['opening_balance'] ?? 0,
            'is_active' => $validated['is_active'] ?? false,
            'notes' => $validated['notes'] ?? null,
            'updated_by' => (int) $user->id,
        ]);

        $freshAccount = $foundAccount->fresh();
        $newValues = $this->auditPayload($freshAccount);

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
                action: 'accounting_account.updated',
                description: '更新會計科目',
                metadata: ['module' => 'accounting_accounts'],
                subject: $freshAccount,
                oldValues: $changedOldValues,
                newValues: $changedNewValues,
                request: $request,
                event: 'accounting_account.updated',
            );
        }

        return redirect()->route('employee-system.accounting.accounts.index');
    }

    private function scopedAccountQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $userCompanyId = (int) ($user?->company_id ?? 0);
        $userBranchId = $user?->branch_id;

        return AccountingAccount::query()
            ->where('company_id', $userCompanyId)
            ->when($userBranchId !== null, fn (Builder $query) => $query->where(function (Builder $branchQuery) use ($userBranchId): void {
                $branchQuery->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $userBranchId);
            }));
    }

    private function accountListPayload(AccountingAccount $account): array
    {
        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'opening_balance' => (string) $account->opening_balance,
            'is_active' => (bool) $account->is_active,
            'notes' => $account->notes,
            'operator_name' => $account->updater?->name ?? $account->creator?->name,
            'updated_at' => optional($account->updated_at)->toISOString(),
        ];
    }

    private function accountEditPayload(AccountingAccount $account): array
    {
        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'opening_balance' => (string) $account->opening_balance,
            'is_active' => (bool) $account->is_active,
            'notes' => $account->notes,
        ];
    }

    private function auditPayload(AccountingAccount $account): array
    {
        return [
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'opening_balance' => (string) $account->opening_balance,
            'is_active' => (bool) $account->is_active,
        ];
    }
}