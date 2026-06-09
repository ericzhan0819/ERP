<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountingEventAccountMappingRequest;
use App\Http\Requests\UpdateAccountingEventAccountMappingRequest;
use App\Models\AccountingAccount;
use App\Models\AccountingEventAccountMapping;
use App\Services\AuditLogService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountingEventAccountMappingController extends Controller
{
    private const EVENT_TYPE = 'vehicle_sale_completed';

    /**
     * @var array<int, string>
     */
    private const MAPPING_KEYS = ['accounts_receivable_account', 'sales_revenue_account'];

    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * 技術註解：列表只輸出 UI 必要 allowlist，不回傳 company_id、branch_id、created_by、updated_by，降低 tenant 與 actor 欄位外洩風險。
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AccountingEventAccountMapping::class);

        $filters = $request->validate([
            'event_type' => ['nullable', 'string', Rule::in([self::EVENT_TYPE])],
            'mapping_key' => ['nullable', 'string', Rule::in(self::MAPPING_KEYS)],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        $mappings = $this->scopedMappingQuery($request->user())
            ->with(['account:id,code,name,type,is_active', 'creator:id,name', 'updater:id,name'])
            ->where('event_type', $filters['event_type'] ?? self::EVENT_TYPE)
            ->when(($filters['mapping_key'] ?? '') !== '', fn (Builder $query) => $query->where('mapping_key', $filters['mapping_key']))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', $filters['is_active'] === '1'))
            ->orderBy('event_type')
            ->orderBy('mapping_key')
            ->orderByRaw('branch_id is not null')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AccountingEventAccountMapping $mapping): array => $this->mappingListPayload($mapping));

        return Inertia::render('Accounting/EventMappings/Index', [
            'mappings' => $mappings,
            'filters' => [
                'event_type' => $filters['event_type'] ?? self::EVENT_TYPE,
                'mapping_key' => $filters['mapping_key'] ?? '',
                'is_active' => array_key_exists('is_active', $filters) ? (string) ($filters['is_active'] ?? '') : '',
            ],
            'supportedEventTypes' => $this->supportedEventTypes(),
            'mappingKeyOptions' => $this->mappingKeyOptions(),
            'can' => $this->canFlags($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', AccountingEventAccountMapping::class);

        return Inertia::render('Accounting/EventMappings/Create', $this->formOptions($request->user()));
    }

    /**
     * 技術註解：mapping 建立時租戶、來源類型與 actor 一律由後端決定，避免 mass assignment 與錯誤 runtime 來源被前端注入。
     */
    public function store(StoreAccountingEventAccountMappingRequest $request)
    {
        $this->authorize('create', AccountingEventAccountMapping::class);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $validated = $this->validatedBusinessRules($request->validated(), $user);

        $mapping = AccountingEventAccountMapping::create([
            'company_id' => (int) $user->company_id,
            'branch_id' => $validated['branch_id'],
            'event_type' => self::EVENT_TYPE,
            'source_type' => $this->sourceType(),
            'mapping_key' => $validated['mapping_key'],
            'account_id' => (int) $validated['account_id'],
            'is_active' => $validated['is_active'],
            'notes' => $validated['notes'],
            'created_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ]);

        $this->auditLogService->log(
            actor: $user,
            action: 'accounting_event_mapping.created',
            description: '新增會計事件映射',
            metadata: ['module' => 'accounting_event_mappings'],
            subject: $mapping,
            newValues: $this->auditPayload($mapping),
            request: $request,
            event: 'accounting_event_mapping.created',
        );

        return redirect()->route('employee-system.accounting.event-mappings.index')->with('success', '會計事件映射已建立');
    }

    public function edit(Request $request, int $mapping): Response
    {
        $foundMapping = $this->scopedMappingQuery($request->user())->whereKey($mapping)->firstOrFail();
        $this->authorize('update', $foundMapping);

        return Inertia::render('Accounting/EventMappings/Edit', array_merge($this->formOptions($request->user()), [
            'mapping' => $this->mappingEditPayload($foundMapping),
        ]));
    }

    /**
     * 技術註解：更新只允許 mapping 業務欄位，不接受 company/source/event/actor 覆寫以防權限提升與錯誤來源轉換。
     */
    public function update(UpdateAccountingEventAccountMappingRequest $request, int $mapping)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $foundMapping = $this->scopedMappingQuery($user)->whereKey($mapping)->firstOrFail();
        $this->authorize('update', $foundMapping);

        $validated = $this->validatedBusinessRules($request->validated(), $user, $foundMapping);
        $oldValues = $this->auditPayload($foundMapping);

        $foundMapping->update([
            'branch_id' => $validated['branch_id'],
            'mapping_key' => $validated['mapping_key'],
            'account_id' => (int) $validated['account_id'],
            'is_active' => $validated['is_active'],
            'notes' => $validated['notes'],
            'updated_by' => (int) $user->id,
        ]);

        $freshMapping = $foundMapping->fresh();
        $newValues = $this->auditPayload($freshMapping);

        if ($oldValues !== $newValues) {
            $this->auditLogService->log(
                actor: $user,
                action: 'accounting_event_mapping.updated',
                description: '更新會計事件映射',
                metadata: ['module' => 'accounting_event_mappings'],
                subject: $freshMapping,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request,
                event: 'accounting_event_mapping.updated',
            );
        }

        return redirect()->route('employee-system.accounting.event-mappings.index')->with('success', '會計事件映射已更新');
    }

    private function scopedMappingQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $userBranchId = $user?->branch_id;

        return AccountingEventAccountMapping::query()
            ->where('company_id', (int) ($user?->company_id ?? 0))
            ->when($userBranchId !== null, fn (Builder $query) => $query->where(function (Builder $branchQuery) use ($userBranchId): void {
                $branchQuery->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $userBranchId);
            }));
    }

    private function scopedAccountQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $userBranchId = $user?->branch_id;

        return AccountingAccount::query()
            ->where('company_id', (int) ($user?->company_id ?? 0))
            ->where('is_active', true)
            ->when($userBranchId !== null, fn (Builder $query) => $query->where(function (Builder $branchQuery) use ($userBranchId): void {
                $branchQuery->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $userBranchId);
            }));
    }

    /**
     * 技術註解：跨租戶、分店、停用科目與錯誤科目類型都在後端二次驗證，避免前端選項被竄改導致未來傳票映射錯誤。
     *
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function validatedBusinessRules(array $validated, Authenticatable $user, ?AccountingEventAccountMapping $current = null): array
    {
        /** @var \App\Models\User $user */
        if (($validated['event_type'] ?? null) !== self::EVENT_TYPE) {
            throw ValidationException::withMessages(['event_type' => '目前只支援車輛交易完成事件。']);
        }

        $branchId = array_key_exists('branch_id', $validated) && $validated['branch_id'] !== null ? (int) $validated['branch_id'] : null;
        if ($user->branch_id !== null && $branchId !== null && (int) $user->branch_id !== $branchId) {
            throw ValidationException::withMessages(['branch_id' => '分店映射只能使用公司預設或目前分店。']);
        }

        $account = AccountingAccount::query()->whereKey((int) $validated['account_id'])->firstOrFail();
        if ((int) $account->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages(['account_id' => '科目不屬於目前公司。']);
        }
        if ($account->branch_id !== null && ($branchId === null || (int) $account->branch_id !== $branchId)) {
            throw ValidationException::withMessages(['account_id' => '分店科目只能用於相同分店映射。']);
        }
        if (! $account->is_active) {
            throw ValidationException::withMessages(['account_id' => '科目必須為啟用狀態。']);
        }

        $metadata = $this->mappingMetadata((string) $validated['mapping_key']);
        $intendedTypes = $metadata['intended_account_types'] ?? [];
        if (! is_array($intendedTypes) || ! in_array($account->type, $intendedTypes, true)) {
            throw ValidationException::withMessages(['account_id' => '科目類型不符合此映射鍵。']);
        }

        $isActive = (bool) ($validated['is_active'] ?? false);
        if ($isActive && $this->hasDuplicateActiveMapping((int) $user->company_id, $branchId, (string) $validated['mapping_key'], $current)) {
            throw ValidationException::withMessages(['mapping_key' => '相同範圍已有啟用中的映射。']);
        }

        return [
            'branch_id' => $branchId,
            'mapping_key' => (string) $validated['mapping_key'],
            'account_id' => (int) $validated['account_id'],
            'is_active' => $isActive,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function hasDuplicateActiveMapping(int $companyId, ?int $branchId, string $mappingKey, ?AccountingEventAccountMapping $current = null): bool
    {
        return AccountingEventAccountMapping::query()
            ->where('company_id', $companyId)
            ->where('event_type', self::EVENT_TYPE)
            ->where('source_type', $this->sourceType())
            ->where('mapping_key', $mappingKey)
            ->where('is_active', true)
            ->when($branchId === null, fn (Builder $query) => $query->whereNull('branch_id'), fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($current !== null, fn (Builder $query) => $query->whereKeyNot($current->id))
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    private function supportedEventTypes(): array
    {
        return [self::EVENT_TYPE => (string) config('accounting_event_mappings.event_types.'.self::EVENT_TYPE.'.label', '車輛交易完成')];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function mappingKeyOptions(): array
    {
        return collect(self::MAPPING_KEYS)->mapWithKeys(fn (string $key): array => [
            $key => [
                'label' => (string) ($this->mappingMetadata($key)['label'] ?? $key),
                'intended_account_types' => $this->mappingMetadata($key)['intended_account_types'] ?? [],
            ],
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mappingMetadata(string $key): array
    {
        $metadata = config('accounting_event_mappings.event_types.'.self::EVENT_TYPE.'.mapping_keys.'.$key, []);

        return is_array($metadata) ? $metadata : [];
    }

    private function sourceType(): string
    {
        return (string) config('accounting_event_mappings.event_types.'.self::EVENT_TYPE.'.source_type', 'vehicle_sale_completion');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Authenticatable $user): array
    {
        return [
            'supportedEventTypes' => $this->supportedEventTypes(),
            'mappingKeyOptions' => $this->mappingKeyOptions(),
            'accountOptions' => $this->accountOptions($user),
            'branchOptions' => $this->branchOptions($user),
            'can' => $this->canFlags($user),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accountOptions(Authenticatable $user): array
    {
        $accountTypes = config('accounting.account_types', []);

        return $this->scopedAccountQuery($user)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'branch_id'])
            ->map(fn (AccountingAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => $accountTypes[$account->type] ?? $account->type,
                'branch_scope_label' => $account->branch_id === null ? '公司預設' : '目前分店',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function branchOptions(Authenticatable $user): array
    {
        /** @var \App\Models\User $user */
        $options = [['value' => null, 'key' => 'company_default', 'label' => '公司預設']];

        if ($user->branch_id !== null) {
            $options[] = ['value' => (int) $user->branch_id, 'key' => 'current_branch', 'label' => '目前分店'];
        }

        return $options;
    }

    /**
     * @return array<string, bool>
     */
    private function canFlags(?Authenticatable $user): array
    {
        return [
            'create' => $user?->can('module.accounting.event-mappings.create') ?? false,
            'update' => $user?->can('module.accounting.event-mappings.update') ?? false,
        ];
    }

    private function mappingListPayload(AccountingEventAccountMapping $mapping): array
    {
        $accountTypes = config('accounting.account_types', []);

        return [
            'id' => $mapping->id,
            'event_type' => $mapping->event_type,
            'event_type_label' => $this->supportedEventTypes()[$mapping->event_type] ?? $mapping->event_type,
            'mapping_key' => $mapping->mapping_key,
            'mapping_key_label' => $this->mappingKeyOptions()[$mapping->mapping_key]['label'] ?? $mapping->mapping_key,
            'account' => $mapping->account ? [
                'id' => $mapping->account->id,
                'code' => $mapping->account->code,
                'name' => $mapping->account->name,
                'type' => $mapping->account->type,
                'type_label' => $accountTypes[$mapping->account->type] ?? $mapping->account->type,
                'is_active' => (bool) $mapping->account->is_active,
            ] : null,
            'branch_scope_label' => $mapping->branch_id === null ? '公司預設' : '分店覆寫',
            'is_active' => (bool) $mapping->is_active,
            'operator_name' => $mapping->updater?->name ?? $mapping->creator?->name,
        ];
    }

    private function mappingEditPayload(AccountingEventAccountMapping $mapping): array
    {
        return [
            'id' => $mapping->id,
            'branch_id' => $mapping->branch_id === null ? null : (int) $mapping->branch_id,
            'event_type' => $mapping->event_type,
            'mapping_key' => $mapping->mapping_key,
            'account_id' => (int) $mapping->account_id,
            'is_active' => (bool) $mapping->is_active,
            'notes' => $mapping->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(AccountingEventAccountMapping $mapping): array
    {
        return [
            'event_type' => $mapping->event_type,
            'source_type' => $mapping->source_type,
            'mapping_key' => $mapping->mapping_key,
            'account_id' => (int) $mapping->account_id,
            'is_active' => (bool) $mapping->is_active,
            'notes' => $mapping->notes,
        ];
    }
}
