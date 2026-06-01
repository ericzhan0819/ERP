<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Models\VehicleCost;
use App\Services\AuditLogService;
use App\Services\VehicleStockNumberService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function __construct(
        private readonly VehicleStockNumberService $vehicleStockNumberService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * 技術註解：列表先授權 viewAny，再套用 tenant 範圍查詢，避免跨公司或跨分店資料外洩。
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vehicle::class);
        $canViewVehiclePricing = $request->user()?->can('module.vehicles.pricing.view') ?? false;
        $canUpdateVehiclePricing = $request->user()?->can('module.vehicles.pricing.update') ?? false;

        $lifecycleStatuses = config('vehicles.lifecycle_statuses');
        $allowedLifecycleStatusKeys = array_keys($lifecycleStatuses);
        $search = trim((string) $request->query('search', ''));
        $lifecycleStatus = (string) $request->query('lifecycle_status', '');

        if (! in_array($lifecycleStatus, $allowedLifecycleStatusKeys, true)) {
            // 技術註解：非法狀態值採忽略策略，避免回傳錯誤造成列表頁不穩定，同時不放寬既有 tenant 邊界。
            $lifecycleStatus = '';
        }

        $vehicles = $this->scopedVehicleQuery($request->user())
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('stock_number', 'like', "%{$search}%")
                        ->orWhere('vin', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->when($lifecycleStatus !== '', function (Builder $query) use ($lifecycleStatus): void {
                $query->where('lifecycle_status', $lifecycleStatus);
            })
            ->orderByDesc('id')
            ->paginate(10, [
                'id',
                'company_id',
                'branch_id',
                'stock_number',
                'vin',
                'license_plate',
                'brand',
                'model',
                'model_year',
                'lifecycle_status',
                'asking_price',
                'floor_price',
            ])
            ->through(function (Vehicle $vehicle) use ($canViewVehiclePricing): array {
                $payload = [
                    'id' => $vehicle->id,
                    'company_id' => $vehicle->company_id,
                    'branch_id' => $vehicle->branch_id,
                    'stock_number' => $vehicle->stock_number,
                    'vin' => $vehicle->vin,
                    'license_plate' => $vehicle->license_plate,
                    'brand' => $vehicle->brand,
                    'model' => $vehicle->model,
                    'model_year' => $vehicle->model_year,
                    'lifecycle_status' => $vehicle->lifecycle_status,
                ];

                // 技術註解：價格欄位僅在具備 pricing.view 時輸出，避免未授權列表洩漏敏感金額。
                if ($canViewVehiclePricing) {
                    $payload['asking_price'] = $vehicle->asking_price;
                    $payload['floor_price'] = $vehicle->floor_price;
                }

                return $payload;
            })
            ->withQueryString();

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters' => [
                'search' => $search,
                'lifecycle_status' => $lifecycleStatus,
            ],
            'lifecycleStatuses' => $lifecycleStatuses,
            'can' => [
                // 技術註解：前端按鈕顯示僅作 UX 引導，實際安全仍以後端授權為準。
                'create_vehicle' => $request->user()?->can('create', Vehicle::class) ?? false,
                // 技術註解：Policy update 需要 Vehicle 實例，列表頁改以對應 permission 判斷避免參數不足錯誤。
                'update_vehicle' => $request->user()?->can('module.vehicles.update') ?? false,
                'view_vehicle_pricing' => $canViewVehiclePricing,
                'update_vehicle_pricing' => $canUpdateVehiclePricing,
            ],
        ]);
    }

    /**
     * 技術註解：建立頁僅提供表單所需最小資料，避免額外暴露不必要資訊。
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Vehicle::class);
        $canViewVehiclePricing = $request->user()?->can('module.vehicles.pricing.view') ?? false;
        $canUpdateVehiclePricing = $request->user()?->can('module.vehicles.pricing.update') ?? false;

        return Inertia::render('Vehicles/Create', [
            'lifecycleStatuses' => config('vehicles.lifecycle_statuses'),
            'can' => [
                'create_vehicle' => $request->user()?->can('create', Vehicle::class) ?? false,
                'update_vehicle' => $request->user()?->can('module.vehicles.update') ?? false,
                'view_vehicle_pricing' => $canViewVehiclePricing,
                'update_vehicle_pricing' => $canUpdateVehiclePricing,
            ],
        ]);
    }

    /**
     * 技術註解：建立時強制使用登入者 company/branch，避免前端竄改租戶邊界造成資料污染。
     */
    public function store(StoreVehicleRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $canUpdateVehiclePricing = $user->can('module.vehicles.pricing.update');

        // 技術註解：建立車輛前先強制檢查 tenant 邊界，避免以 company_id=0 或 branch_id=null 呼叫序號服務導致跨租戶污染。
        if ((int) $user->company_id <= 0 || $user->branch_id === null) {
            throw new HttpResponseException(response()->json([
                'message' => '使用者尚未設定公司或分店，無法建立車輛。',
            ], 422));
        }

        $stockNumber = $this->vehicleStockNumberService->generate((int) $user->company_id);

        $createPayload = [
            'company_id' => (int) $user->company_id,
            'branch_id' => (int) $user->branch_id,
            'stock_number' => $stockNumber,
            'vin' => $request->validated('vin'),
            'license_plate' => $request->validated('license_plate'),
            'brand' => $request->validated('brand'),
            'model' => $request->validated('model'),
            'variant' => $request->validated('variant'),
            'model_year' => $request->validated('model_year'),
            'exterior_color' => $request->validated('exterior_color'),
            'interior_color' => $request->validated('interior_color'),
            'odometer_km' => $request->validated('odometer_km'),
            'lifecycle_status' => $request->validated('lifecycle_status'),
            'internal_notes' => $request->validated('internal_notes'),
            'created_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ];

        if ($canUpdateVehiclePricing && $request->exists('asking_price')) {
            $createPayload['asking_price'] = $request->validated('asking_price');
        }

        if ($canUpdateVehiclePricing && $request->exists('floor_price')) {
            $createPayload['floor_price'] = $request->validated('floor_price');
        }

        $vehicle = Vehicle::create($createPayload);

        $this->auditLogService->log(
            actor: $user,
            action: 'vehicle.created',
            description: 'Vehicle created',
            targetUser: null,
            metadata: [],
            subject: $vehicle,
            oldValues: null,
            newValues: [
                // 技術註解：僅保留主要業務欄位，避免將內部備註等潛在敏感內容寫入審計快照。
                'stock_number' => $vehicle->stock_number,
                'vin' => $vehicle->vin,
                'license_plate' => $vehicle->license_plate,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'model_year' => $vehicle->model_year,
                'lifecycle_status' => $vehicle->lifecycle_status,
            ],
            request: $request,
            event: 'vehicle.created',
        );

        return redirect()->route('employee-system.vehicles.show', $vehicle->id);
    }

    /**
     * 技術註解：禁止未 scoped implicit binding，必須先套用 tenant 查詢，查無資料直接 404。
     */
    public function show(Request $request, int $vehicle): Response
    {
        $lifecycleStatuses = config('vehicles.lifecycle_statuses');
        $canViewVehiclePricing = $request->user()?->can('module.vehicles.pricing.view') ?? false;
        $canUpdateVehiclePricing = $request->user()?->can('module.vehicles.pricing.update') ?? false;
        $canViewVehicleCosts = $request->user()?->can('module.vehicles.costs.view') ?? false;
        $canCreateVehicleCosts = $request->user()?->can('module.vehicles.costs.create') ?? false;
        $canUpdateVehicleCosts = $request->user()?->can('module.vehicles.costs.update') ?? false;

        $foundVehicle = $this->scopedVehicleQuery($request->user())
            ->with([
                'company:id,name,code',
                'branch:id,name,code',
                'creator:id,name',
                'updater:id,name',
            ])
            ->whereKey($vehicle)
            ->firstOrFail();

        // 技術註解：查到資料後仍需再次經過 policy，防止權限提升或未預期授權繞過。
        $this->authorize('view', $foundVehicle);

        $vehicleCosts = null;
        $vehicleCostSummary = null;
        $vehicleCostTypes = null;
        $vehicleCostPaymentStatuses = null;

        // 技術註解：僅在具備 costs.view 時查詢與輸出成本資料，避免未授權使用者取得任何財務資訊。
        if ($canViewVehicleCosts) {
            $costTypes = config('vehicles.vehicle_cost_types', []);
            $paymentStatuses = config('vehicles.vehicle_cost_payment_statuses', []);

            $costRows = $foundVehicle->costs()
                ->with(['creator:id,name', 'updater:id,name'])
                ->orderByDesc('cost_date')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'cost_type',
                    'description',
                    'amount',
                    'cost_date',
                    'vendor_name',
                    'payment_status',
                    'paid_at',
                    'created_by',
                    'updated_by',
                ]);

            $vehicleCosts = $costRows->map(function (VehicleCost $cost) use ($costTypes, $paymentStatuses): array {
                return [
                    'id' => $cost->id,
                    'cost_type' => $cost->cost_type,
                    'cost_type_label' => $costTypes[$cost->cost_type] ?? $cost->cost_type,
                    'description' => $cost->description,
                    'amount' => $cost->amount,
                    'cost_date' => $cost->cost_date,
                    'vendor_name' => $cost->vendor_name,
                    'payment_status' => $cost->payment_status,
                    'payment_status_label' => $paymentStatuses[$cost->payment_status] ?? $cost->payment_status,
                    'paid_at' => $cost->paid_at,
                    'creator' => $cost->creator ? ['name' => $cost->creator->name] : null,
                    'updater' => $cost->updater ? ['name' => $cost->updater->name] : null,
                ];
            })->values();

            $vehicleCostSummary = [
                'total_amount' => (string) $costRows->sum(fn (VehicleCost $cost): float => (float) $cost->amount),
                'unpaid_amount' => (string) $costRows->where('payment_status', 'unpaid')->sum(fn (VehicleCost $cost): float => (float) $cost->amount),
                'paid_amount' => (string) $costRows->where('payment_status', 'paid')->sum(fn (VehicleCost $cost): float => (float) $cost->amount),
                'partially_paid_amount' => (string) $costRows->where('payment_status', 'partially_paid')->sum(fn (VehicleCost $cost): float => (float) $cost->amount),
                'count' => $costRows->count(),
            ];

            // 技術註解：Show 頁已改為只讀，不再回傳建立/編輯成本所需字典，降低不必要敏感流程暴露。
            $vehicleCostTypes = null;
            $vehicleCostPaymentStatuses = null;
        }

        return Inertia::render('Vehicles/Show', [
            'vehicle' => [
                'id' => $foundVehicle->id,
                'stock_number' => $foundVehicle->stock_number,
                'vin' => $foundVehicle->vin,
                'brand' => $foundVehicle->brand,
                'model' => $foundVehicle->model,
                'variant' => $foundVehicle->variant,
                'model_year' => $foundVehicle->model_year,
                'license_plate' => $foundVehicle->license_plate,
                'exterior_color' => $foundVehicle->exterior_color,
                'interior_color' => $foundVehicle->interior_color,
                'odometer_km' => $foundVehicle->odometer_km,
                'lifecycle_status' => $foundVehicle->lifecycle_status,
                'internal_notes' => $foundVehicle->internal_notes,
                // 技術註解：補齊系統資訊欄位，避免前端詳情頁因 payload 缺值而無法顯示建立/更新資訊。
                'created_at' => $foundVehicle->created_at,
                'updated_at' => $foundVehicle->updated_at,
                'company' => $foundVehicle->company ? [
                    'name' => $foundVehicle->company->name,
                    'code' => $foundVehicle->company->code,
                ] : null,
                'branch' => $foundVehicle->branch ? [
                    'name' => $foundVehicle->branch->name,
                    'code' => $foundVehicle->branch->code,
                ] : null,
                'creator' => $foundVehicle->creator ? [
                    'name' => $foundVehicle->creator->name,
                ] : null,
                'updater' => $foundVehicle->updater ? [
                    'name' => $foundVehicle->updater->name,
                ] : null,
                // 技術註解：價格欄位僅在具備 pricing.view 時輸出，避免未授權詳情頁洩漏敏感金額。
                ...($canViewVehiclePricing ? [
                    'asking_price' => $foundVehicle->asking_price,
                    'floor_price' => $foundVehicle->floor_price,
                ] : []),
            ],
            'lifecycleStatuses' => $lifecycleStatuses,
            'vehicleCosts' => $vehicleCosts,
            'vehicleCostSummary' => $vehicleCostSummary,
            'vehicleCostTypes' => $vehicleCostTypes,
            'vehicleCostPaymentStatuses' => $vehicleCostPaymentStatuses,
            'can' => [
                'create_vehicle' => $request->user()?->can('create', Vehicle::class) ?? false,
                'update_vehicle' => $request->user()?->can('update', $foundVehicle) ?? false,
                'view_vehicle_pricing' => $canViewVehiclePricing,
                'update_vehicle_pricing' => $canUpdateVehiclePricing,
                'view_vehicle_costs' => $canViewVehicleCosts,
                'create_vehicle_costs' => $canCreateVehicleCosts,
                'update_vehicle_costs' => $canUpdateVehicleCosts,
            ],
        ]);
    }

    /**
     * 技術註解：編輯頁必須先 scoped 查詢再 authorize，避免使用未隔離資料進行權限判斷。
     */
    public function edit(Request $request, int $vehicle): Response
    {
        $lifecycleStatuses = config('vehicles.lifecycle_statuses');
        $canViewVehiclePricing = $request->user()?->can('module.vehicles.pricing.view') ?? false;
        $canUpdateVehiclePricing = $request->user()?->can('module.vehicles.pricing.update') ?? false;
        $canViewVehicleCosts = $request->user()?->can('module.vehicles.costs.view') ?? false;
        $canCreateVehicleCosts = $request->user()?->can('module.vehicles.costs.create') ?? false;
        $canUpdateVehicleCosts = $request->user()?->can('module.vehicles.costs.update') ?? false;

        $foundVehicle = $this->scopedVehicleQuery($request->user())
            ->whereKey($vehicle)
            ->firstOrFail();

        $this->authorize('update', $foundVehicle);

        $vehicleCosts = null;
        $vehicleCostSummary = null;
        $vehicleCostTypes = null;
        $vehicleCostPaymentStatuses = null;

        // 技術註解：編輯頁成本 payload 嚴格比照 show，僅在具備 costs.view 時回傳，避免未授權者取得財務敏感資訊。
        if ($canViewVehicleCosts) {
            $costTypes = config('vehicles.vehicle_cost_types', []);
            $paymentStatuses = config('vehicles.vehicle_cost_payment_statuses', []);

            $costRows = $foundVehicle->costs()
                ->with(['creator:id,name', 'updater:id,name'])
                ->orderByDesc('cost_date')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'cost_type',
                    'description',
                    'amount',
                    'cost_date',
                    'vendor_name',
                    'payment_status',
                    'paid_at',
                    'created_by',
                    'updated_by',
                ]);

            $vehicleCosts = $costRows->map(function (VehicleCost $cost) use ($costTypes, $paymentStatuses): array {
                return [
                    'id' => $cost->id,
                    'cost_type' => $cost->cost_type,
                    'cost_type_label' => $costTypes[$cost->cost_type] ?? $cost->cost_type,
                    'description' => $cost->description,
                    'amount' => $cost->amount,
                    'cost_date' => $cost->cost_date,
                    'vendor_name' => $cost->vendor_name,
                    'payment_status' => $cost->payment_status,
                    'payment_status_label' => $paymentStatuses[$cost->payment_status] ?? $cost->payment_status,
                    'paid_at' => $cost->paid_at,
                    'creator' => $cost->creator ? ['name' => $cost->creator->name] : null,
                    'updater' => $cost->updater ? ['name' => $cost->updater->name] : null,
                ];
            })->values();

            $vehicleCostSummary = [
                'total_amount' => (string) $costRows->sum(fn (VehicleCost $cost): float => (float) $cost->amount),
                'unpaid_amount' => (string) $costRows->where('payment_status', 'unpaid')->sum(fn (VehicleCost $cost): float => (float) $cost->amount),
                'paid_amount' => (string) $costRows->where('payment_status', 'paid')->sum(fn (VehicleCost $cost): float => (float) $cost->amount),
                'partially_paid_amount' => (string) $costRows->where('payment_status', 'partially_paid')->sum(fn (VehicleCost $cost): float => (float) $cost->amount),
                'count' => $costRows->count(),
            ];

            $vehicleCostTypes = $costTypes;
            $vehicleCostPaymentStatuses = $paymentStatuses;
        }

        return Inertia::render('Vehicles/Edit', [
            'vehicle' => [
                'id' => $foundVehicle->id,
                'stock_number' => $foundVehicle->stock_number,
                'vin' => $foundVehicle->vin,
                'license_plate' => $foundVehicle->license_plate,
                'brand' => $foundVehicle->brand,
                'model' => $foundVehicle->model,
                'variant' => $foundVehicle->variant,
                'model_year' => $foundVehicle->model_year,
                'exterior_color' => $foundVehicle->exterior_color,
                'interior_color' => $foundVehicle->interior_color,
                'odometer_km' => $foundVehicle->odometer_km,
                'lifecycle_status' => $foundVehicle->lifecycle_status,
                'internal_notes' => $foundVehicle->internal_notes,
                // 技術註解：編輯 payload 僅授權 pricing.update 者可取得價格欄位，避免前端形成可編輯敏感資料入口。
                ...($canUpdateVehiclePricing ? [
                    'asking_price' => $foundVehicle->asking_price,
                    'floor_price' => $foundVehicle->floor_price,
                ] : []),
            ],
            'lifecycleStatuses' => $lifecycleStatuses,
            'vehicleCosts' => $vehicleCosts,
            'vehicleCostSummary' => $vehicleCostSummary,
            'vehicleCostTypes' => $vehicleCostTypes,
            'vehicleCostPaymentStatuses' => $vehicleCostPaymentStatuses,
            'can' => [
                'create_vehicle' => $request->user()?->can('create', Vehicle::class) ?? false,
                'update_vehicle' => $request->user()?->can('update', $foundVehicle) ?? false,
                'view_vehicle_pricing' => $canViewVehiclePricing,
                'update_vehicle_pricing' => $canUpdateVehiclePricing,
                'view_vehicle_costs' => $canViewVehicleCosts,
                'create_vehicle_costs' => $canCreateVehicleCosts,
                'update_vehicle_costs' => $canUpdateVehicleCosts,
            ],
        ]);
    }

    /**
     * 技術註解：更新流程維持 scoped 讀取 + policy 授權 + validated allowlist，避免 IDOR 與 mass assignment 風險。
     */
    public function update(UpdateVehicleRequest $request, int $vehicle)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $canUpdateVehiclePricing = $user->can('module.vehicles.pricing.update');

        $foundVehicle = $this->scopedVehicleQuery($user)
            ->whereKey($vehicle)
            ->firstOrFail();

        $this->authorize('update', $foundVehicle);

        $originalValues = $foundVehicle->only([
            'vin',
            'license_plate',
            'brand',
            'model',
            'variant',
            'model_year',
            'exterior_color',
            'interior_color',
            'odometer_km',
            'lifecycle_status',
            'internal_notes',
            'asking_price',
            'floor_price',
        ]);

        $updatePayload = [
            'vin' => $request->validated('vin'),
            'license_plate' => $request->validated('license_plate'),
            'brand' => $request->validated('brand'),
            'model' => $request->validated('model'),
            'variant' => $request->validated('variant'),
            'model_year' => $request->validated('model_year'),
            'exterior_color' => $request->validated('exterior_color'),
            'interior_color' => $request->validated('interior_color'),
            'odometer_km' => $request->validated('odometer_km'),
            'lifecycle_status' => $request->validated('lifecycle_status'),
            'internal_notes' => $request->validated('internal_notes'),
            'updated_by' => (int) $user->id,
        ];

        if ($canUpdateVehiclePricing && $request->exists('asking_price')) {
            $updatePayload['asking_price'] = $request->validated('asking_price');
        }

        if ($canUpdateVehiclePricing && $request->exists('floor_price')) {
            $updatePayload['floor_price'] = $request->validated('floor_price');
        }

        $foundVehicle->update($updatePayload);

        $newValues = $foundVehicle->only(array_keys($originalValues));
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
                action: 'vehicle.updated',
                description: 'Vehicle updated',
                targetUser: null,
                metadata: [],
                subject: $foundVehicle,
                oldValues: $changedOldValues,
                newValues: $changedNewValues,
                request: $request,
                event: 'vehicle.updated',
            );
        }

        return redirect()->route('employee-system.vehicles.show', $foundVehicle->id);
    }

    /**
     * 技術註解：集中 tenant 範圍，先 company 再 branch，避免 IDOR 與跨邊界讀取。
     */
    private function scopedVehicleQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $userCompanyId = (int) ($user?->company_id ?? 0);
        $userBranchId = $user?->branch_id;

        $query = Vehicle::query()->where('company_id', $userCompanyId);

        if ($userBranchId !== null) {
            $query->where('branch_id', (int) $userBranchId);
        }

        return $query;
    }
}
