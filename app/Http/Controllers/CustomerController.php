<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\VehicleSale;
use App\Services\AuditLogService;
use App\Services\CustomerNumberService;
use App\Services\ReceivableSummaryService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerNumberService $customerNumberService,
        private readonly AuditLogService $auditLogService,
        private readonly ReceivableSummaryService $receivableSummaryService,
    ) {}

    /**
     * 技術註解：列表先授權再套 tenant scope，且不輸出敏感個資欄位，避免列表成為個資外洩入口。
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $statuses = config('customers.statuses');
        $allowedStatusKeys = array_keys($statuses);
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        if (! in_array($status, $allowedStatusKeys, true)) {
            // 技術註解：非法狀態採忽略，避免放寬查詢且保持列表頁可用性。
            $status = '';
        }

        $customers = $this->scopedCustomerQuery($request->user())
            ->when($q !== '', function (Builder $query) use ($q): void {
                $query->where(function (Builder $subQuery) use ($q): void {
                    $subQuery->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('secondary_phone', 'like', "%{$q}%")
                        ->orWhere('customer_number', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(10, [
                'id',
                'customer_number',
                'name',
                'phone',
                'status',
                'source',
                'created_at',
                'updated_at',
            ])
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'q' => $q,
                'status' => $status,
            ],
            'customerStatuses' => $statuses,
            'can' => $this->customerCapabilities($request->user()),
        ]);
    }

    /**
     * 技術註解：建立頁只回傳表單字典與能力旗標，避免外洩非必要客戶資料。
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('Customers/Create', [
            'customerStatuses' => config('customers.statuses'),
            'can' => $this->customerCapabilities($request->user()),
        ]);
    }

    /**
     * 技術註解：建立時租戶、流水號與建立者皆由後端決定，避免前端覆寫造成 IDOR 或審計污染。
     */
    public function store(StoreCustomerRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ((int) $user->company_id <= 0 || $user->branch_id === null) {
            throw new HttpResponseException(response()->json([
                'message' => '使用者尚未設定公司或分店，無法建立客戶。',
            ], 422));
        }

        $customerNumber = $this->customerNumberService->generate((int) $user->company_id);
        $validated = $request->validated();

        $customer = Customer::create(array_merge($this->generalPayload($validated), $this->sensitivePayload($validated), [
            'company_id' => (int) $user->company_id,
            'branch_id' => (int) $user->branch_id,
            'customer_number' => $customerNumber,
            'created_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ]));

        $this->auditLogService->log(
            actor: $user,
            action: 'customer.created',
            description: '新增客戶',
            targetUser: null,
            metadata: ['module' => 'customers'],
            subject: $customer,
            oldValues: null,
            newValues: $this->auditPayload($customer),
            request: $request,
            event: 'customer.created',
        );

        return redirect()->route('employee-system.customers.show', $customer->id);
    }

    /**
     * 技術註解：show 使用 scoped query + firstOrFail，跨 tenant 優先 404 以避免 IDOR 探測。
     */
    public function show(Request $request, int $customer): Response
    {
        $foundCustomer = $this->scopedCustomerQuery($request->user())
            ->with(['creator:id,name', 'updater:id,name'])
            ->whereKey($customer)
            ->firstOrFail();

        $this->authorize('view', $foundCustomer);

        return Inertia::render('Customers/Show', [
            'customer' => $this->customerPayload($request, $foundCustomer),
            'customerTransactions' => $this->customerTransactionsPayload($request, $foundCustomer),
            'customerStatuses' => config('customers.statuses'),
            'can' => $this->customerCapabilities($request->user(), $foundCustomer),
        ]);
    }

    /**
     * 技術註解：edit 同樣先 scoped 查詢再授權，一般編輯權限不代表可看到或更新敏感個資。
     */
    public function edit(Request $request, int $customer): Response
    {
        $foundCustomer = $this->scopedCustomerQuery($request->user())
            ->whereKey($customer)
            ->firstOrFail();

        $this->authorize('update', $foundCustomer);

        return Inertia::render('Customers/Edit', [
            'customer' => $this->customerPayload($request, $foundCustomer),
            'customerStatuses' => config('customers.statuses'),
            'can' => $this->customerCapabilities($request->user(), $foundCustomer),
        ]);
    }

    /**
     * 技術註解：更新採 scoped 查詢、Policy 授權與 validated allowlist，避免 IDOR 與 mass assignment。
     */
    public function update(UpdateCustomerRequest $request, int $customer)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $foundCustomer = $this->scopedCustomerQuery($user)
            ->whereKey($customer)
            ->firstOrFail();

        $this->authorize('update', $foundCustomer);

        $validated = $request->validated();
        $originalValues = $this->auditPayload($foundCustomer);
        $foundCustomer->update(array_merge($this->generalPayload($validated), $this->sensitivePayload($validated), [
            'updated_by' => (int) $user->id,
        ]));

        $newValues = $this->auditPayload($foundCustomer->fresh());
        $changedOldValues = [];
        $changedNewValues = [];

        foreach ($originalValues as $field => $oldValue) {
            if ($oldValue !== $newValues[$field]) {
                $changedOldValues[$field] = $oldValue;
                $changedNewValues[$field] = $newValues[$field];
            }
        }

        if ($changedNewValues !== []) {
            $this->auditLogService->log(
                actor: $user,
                action: 'customer.updated',
                description: '更新客戶資料',
                targetUser: null,
                metadata: ['module' => 'customers'],
                subject: $foundCustomer,
                oldValues: $changedOldValues,
                newValues: $changedNewValues,
                request: $request,
                event: 'customer.updated',
            );
        }

        return redirect()->route('employee-system.customers.show', $foundCustomer->id);
    }

    private function scopedCustomerQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $userCompanyId = (int) ($user?->company_id ?? 0);
        $userBranchId = $user?->branch_id;

        $query = Customer::query()->where('company_id', $userCompanyId);

        if ($userBranchId !== null) {
            $query->where('branch_id', (int) $userBranchId);
        }

        return $query;
    }

    private function scopedVehicleSaleQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $query = VehicleSale::query()->where('company_id', (int) ($user?->company_id ?? 0));

        if ($user?->branch_id !== null) {
            $query->where('branch_id', (int) $user->branch_id);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(Request $request, Customer $customer): array
    {
        $payload = [
            'id' => $customer->id,
            'customer_number' => $customer->customer_number,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'secondary_phone' => $customer->secondary_phone,
            'email' => $customer->email,
            'line_id' => $customer->line_id,
            'status' => $customer->status,
            'source' => $customer->source,
            'notes' => $customer->notes,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
            'creator' => $customer->creator ? ['name' => $customer->creator->name] : null,
            'updater' => $customer->updater ? ['name' => $customer->updater->name] : null,
        ];

        // 技術註解：敏感個資只在具備 sensitive.view 時輸出，避免 Inertia props 成為資料外洩面。
        if ($request->user()?->can('viewSensitive', $customer)) {
            $payload['id_number'] = $customer->id_number;
            $payload['birthday'] = optional($customer->birthday)->format('Y-m-d');
            $payload['address'] = $customer->address;
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function customerTransactionsPayload(Request $request, Customer $customer): ?array
    {
        $user = $request->user();

        if (! ($user?->can('module.vehicles.sales.view') ?? false)) {
            return null;
        }

        $canViewReceivables = $user?->can('module.receivables.view') ?? false;

        $relations = [
            'vehicle' => fn ($query) => $query
                ->where('company_id', (int) $customer->company_id)
                ->where('branch_id', (int) $customer->branch_id)
                ->select('id', 'company_id', 'branch_id', 'stock_number', 'brand', 'model', 'license_plate'),
        ];

        if ($canViewReceivables) {
            // 技術註解：只有應收權限者才載入 payments，避免無權使用者即使 payload 未輸出仍觸發不必要的敏感收款關聯查詢。
            $relations['payments'] = fn ($query) => $query
                ->where('company_id', (int) $customer->company_id)
                ->where('branch_id', (int) $customer->branch_id);
        }

        return $this->scopedVehicleSaleQuery($user)
            // 技術註解：客戶交易紀錄僅接受正式 customer_id 關聯，刻意不使用 snapshot 姓名/電話模糊比對，避免錯誤歸戶與個資外洩。
            ->where('customer_id', $customer->id)
            ->where('company_id', (int) $customer->company_id)
            ->where('branch_id', (int) $customer->branch_id)
            // 技術註解：關聯資料仍額外套 tenant 條件，避免錯誤 FK 指到跨租戶紀錄時形成 IDOR 洩漏。
            ->with($relations)
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (VehicleSale $sale): array => $this->transactionPayload($sale, $canViewReceivables))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function transactionPayload(VehicleSale $sale, bool $canViewReceivables): array
    {
        $payload = [
            'id' => $sale->id,
            'sale_status' => $sale->sale_status,
            'sale_status_label' => config('vehicle_sales.sale_statuses.'.$sale->sale_status, $sale->sale_status),
            'sale_price' => $sale->sale_price,
            'sold_at' => optional($sale->sold_at)->format('Y-m-d'),
            'salesperson_name' => $sale->salesperson_name,
            'vehicle' => $sale->vehicle ? [
                'stock_number' => $sale->vehicle->stock_number,
                'brand' => $sale->vehicle->brand,
                'model' => $sale->vehicle->model,
                'license_plate' => $sale->vehicle->license_plate,
            ] : null,
            'links' => [
                'vehicle_show_url' => $sale->vehicle ? route('employee-system.vehicles.show', $sale->vehicle->id) : null,
            ],
        ];

        if ($canViewReceivables) {
            $summary = $this->receivableSummaryService->summarize($sale);
            $payload['receivable_summary'] = collect($summary)->only([
                'receivable_amount',
                'received_amount',
                'receivable_balance',
                'receivable_status',
                'receivable_status_label',
                'received_payment_count',
                'payment_record_count',
            ])->all();
            $payload['links']['receivable_show_url'] = route('employee-system.receivables.show', $sale->id);
        }

        return $payload;
    }

    /**
     * @return array<string, bool>
     */
    private function customerCapabilities(?Authenticatable $user, ?Customer $customer = null): array
    {
        return [
            'create_customers' => $user?->can('create', Customer::class) ?? false,
            'update_customers' => $customer ? ($user?->can('update', $customer) ?? false) : ($user?->can('module.customers.update') ?? false),
            'view_customer_sensitive' => $customer ? ($user?->can('viewSensitive', $customer) ?? false) : ($user?->can('module.customers.sensitive.view') ?? false),
            'update_customer_sensitive' => $customer ? ($user?->can('updateSensitive', $customer) ?? false) : ($user?->can('module.customers.sensitive.update') ?? false),
            'view_customer_transactions' => $user?->can('module.vehicles.sales.view') ?? false,
            'view_customer_transaction_receivables' => $user?->can('module.receivables.view') ?? false,
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function generalPayload(array $validated): array
    {
        return collect($validated)->only([
            'name',
            'phone',
            'secondary_phone',
            'email',
            'line_id',
            'status',
            'source',
            'notes',
        ])->all();
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function sensitivePayload(array $validated): array
    {
        return collect($validated)->only(['id_number', 'birthday', 'address'])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(Customer $customer): array
    {
        // 技術註解：稽核只記一般白名單欄位，刻意排除個資與租戶/系統欄位以降低敏感資料長期留存風險。
        return $customer->only([
            'customer_number',
            'name',
            'phone',
            'secondary_phone',
            'email',
            'line_id',
            'status',
            'source',
            'notes',
        ]);
    }
}

