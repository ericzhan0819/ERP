<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleCost;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VehicleCostManagementController extends Controller
{
    /**
     * 技術註解：第一階段僅提供 tenant scoped 成本查詢入口，不新增會計、應付或利潤 payload，避免擴張財務資料面。
     */
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        abort_unless($user->can('module.vehicles.costs.view'), 403);

        $costTypes = config('vehicles.vehicle_cost_types', []);
        $paymentStatuses = config('vehicles.vehicle_cost_payment_statuses', []);
        $periodOptions = $this->periodOptions();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'cost_type' => ['nullable', 'string', Rule::in(array_keys($costTypes))],
            'payment_status' => ['nullable', 'string', Rule::in(array_keys($paymentStatuses))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'period' => ['nullable', 'string', Rule::in(array_keys($periodOptions))],
        ]);
        $filters = $this->normalizePeriodFilters($filters, $request);

        $baseQuery = $this->scopedCostQuery($user);
        $this->applyFilters($baseQuery, $filters);

        $summaryQuery = clone $baseQuery;
        $summaryRows = $summaryQuery
            ->selectRaw('COUNT(*) as cost_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) as paid_amount")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_status IN ('unpaid', 'partially_paid') THEN amount ELSE 0 END), 0) as unpaid_amount")
            ->first();

        $costs = $baseQuery
            ->with([
                'vehicle:id,stock_number,brand,model,license_plate,lifecycle_status,company_id,branch_id',
                'creator:id,name',
                'updater:id,name',
            ])
            ->orderByDesc('cost_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (VehicleCost $cost): array => $this->serializeCost($cost));

        return Inertia::render('VehicleCosts/Index', [
            'costs' => $costs,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'cost_type' => $filters['cost_type'] ?? 'all',
                'payment_status' => $filters['payment_status'] ?? 'all',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'period' => $filters['period'],
            ],
            'periodOptions' => $periodOptions,
            'costTypes' => $costTypes,
            'paymentStatuses' => $paymentStatuses,
            'summary' => [
                'total_amount' => (string) ($summaryRows?->total_amount ?? '0.00'),
                'paid_amount' => (string) ($summaryRows?->paid_amount ?? '0.00'),
                'unpaid_amount' => (string) ($summaryRows?->unpaid_amount ?? '0.00'),
                'count' => (int) ($summaryRows?->cost_count ?? 0),
            ],
            'can' => [
                'create_cost' => $user->can('module.vehicles.costs.create'),
                'update_cost' => $user->can('module.vehicles.costs.update'),
                'edit_vehicle' => $user->can('module.vehicles.update'),
            ],
        ]);
    }

    /**
     * 技術註解：獨立新增工作台僅輸出 tenant scoped 車輛選項與成本白名單，不接受前端提供 tenant/actor 欄位以防越權寫入。
     */
    public function create(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        abort_unless($user->can('module.vehicles.costs.create'), 403);

        $validated = $request->validate([
            'vehicle_id' => ['nullable', 'integer'],
        ]);
        $selectedVehicleId = $validated['vehicle_id'] ?? null;

        if ($selectedVehicleId !== null) {
            $this->scopedVehicleQuery($user)->whereKey($selectedVehicleId)->firstOrFail();
        }

        return Inertia::render('VehicleCosts/Create', [
            'vehicleOptions' => $this->vehicleOptions($user),
            'costTypes' => config('vehicles.vehicle_cost_types', []),
            'paymentStatuses' => config('vehicles.vehicle_cost_payment_statuses', []),
            'defaults' => [
                'vehicle_id' => $selectedVehicleId,
                'cost_date' => Carbon::now()->toDateString(),
                'payment_status' => 'unpaid',
            ],
            'can' => [
                'create_cost' => $user->can('module.vehicles.costs.create'),
                'update_cost' => $user->can('module.vehicles.costs.update'),
                'edit_vehicle' => $user->can('module.vehicles.update'),
            ],
        ]);
    }

    /**
     * 技術註解：獨立編輯工作台以成本與關聯車輛雙重 tenant scope 查詢，並經 VehicleCostPolicy update 二次授權防止 IDOR。
     */
    public function edit(Request $request, int $vehicleCost): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        abort_unless($user->can('module.vehicles.costs.update'), 403);

        $cost = $this->scopedCostQuery($user)
            ->with('vehicle:id,stock_number,brand,model,license_plate,lifecycle_status,company_id,branch_id')
            ->whereKey($vehicleCost)
            ->firstOrFail();

        abort_unless($cost->vehicle !== null, 404);
        $this->authorize('update', [$cost, $cost->vehicle]);

        return Inertia::render('VehicleCosts/Edit', [
            'cost' => $this->serializeEditableCost($cost),
            'costTypes' => config('vehicles.vehicle_cost_types', []),
            'paymentStatuses' => config('vehicles.vehicle_cost_payment_statuses', []),
            'can' => [
                'create_cost' => $user->can('module.vehicles.costs.create'),
                'update_cost' => $user->can('module.vehicles.costs.update'),
                'edit_vehicle' => $user->can('module.vehicles.update'),
            ],
        ]);
    }

    /**
     * 技術註解：期間選項集中在後端輸出，避免前端複製查詢口徑並造成 summary 與列表期間不一致。
     *
     * @return array<string, string>
     */
    private function periodOptions(): array
    {
        return [
            'current_month' => '本月',
            'previous_month' => '上月',
            'last_90_days' => '近 90 天',
            'year_to_date' => '今年',
            'all' => '全部',
            'custom' => '自訂',
        ];
    }

    /**
     * 技術註解：預設本月查詢可避免日常管理摘要誤用全期間累計；使用者手動輸入日期時一律視為自訂期間。
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizePeriodFilters(array $filters, Request $request): array
    {
        $hasManualDate = $request->filled('date_from') || $request->filled('date_to');
        $period = $hasManualDate ? 'custom' : (string) ($filters['period'] ?? 'current_month');
        $today = Carbon::now();

        if ($period === 'current_month') {
            $filters['date_from'] = $today->copy()->startOfMonth()->toDateString();
            $filters['date_to'] = $today->toDateString();
        } elseif ($period === 'previous_month') {
            $previousMonth = $today->copy()->subMonthNoOverflow();
            $filters['date_from'] = $previousMonth->copy()->startOfMonth()->toDateString();
            $filters['date_to'] = $previousMonth->copy()->endOfMonth()->toDateString();
        } elseif ($period === 'last_90_days') {
            $filters['date_from'] = $today->copy()->subDays(89)->toDateString();
            $filters['date_to'] = $today->toDateString();
        } elseif ($period === 'year_to_date') {
            $filters['date_from'] = $today->copy()->startOfYear()->toDateString();
            $filters['date_to'] = $today->toDateString();
        } elseif ($period === 'all') {
            $filters['date_from'] = null;
            $filters['date_to'] = null;
        }

        $filters['period'] = $period;

        return $filters;
    }

    /**
     * 技術註解：成本列表同時限制 vehicle_costs 與 vehicle tenant 欄位，降低資料列 tenant 欄位不一致時的外洩風險。
     */
    private function scopedCostQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $userCompanyId = (int) ($user?->company_id ?? 0);
        $userBranchId = $user?->branch_id;

        return VehicleCost::query()
            ->where('company_id', $userCompanyId)
            ->when($userBranchId !== null, fn (Builder $query) => $query->where('branch_id', (int) $userBranchId))
            ->whereHas('vehicle', function (Builder $query) use ($userCompanyId, $userBranchId): void {
                $query->where('company_id', $userCompanyId)
                    ->when($userBranchId !== null, fn (Builder $vehicleQuery) => $vehicleQuery->where('branch_id', (int) $userBranchId));
            });
    }

    /**
     * 技術註解：車輛選項集中套用 tenant scope，避免獨立成本頁洩漏其他公司或分店車輛。
     */
    private function scopedVehicleQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $userCompanyId = (int) ($user?->company_id ?? 0);
        $userBranchId = $user?->branch_id;

        return Vehicle::query()
            ->where('company_id', $userCompanyId)
            ->when($userBranchId !== null, fn (Builder $query) => $query->where('branch_id', (int) $userBranchId));
    }

    /**
     * 技術註解：前端只需要辨識車輛的最小欄位，刻意不輸出 company_id/branch_id 以降低 tenant 資訊外洩面。
     *
     * @return array<int, array<string, mixed>>
     */
    private function vehicleOptions(?Authenticatable $user): array
    {
        return $this->scopedVehicleQuery($user)
            ->select(['id', 'stock_number', 'brand', 'model', 'license_plate', 'lifecycle_status'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'stock_number' => $vehicle->stock_number,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'license_plate' => $vehicle->license_plate,
                'lifecycle_status' => $vehicle->lifecycle_status,
            ])
            ->all();
    }

    /**
     * 技術註解：所有查詢條件皆經 validation 與白名單過濾，避免動態查詢條件引入 injection 或報表口徑漂移。
     *
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $q = trim((string) Arr::get($filters, 'q', ''));

        if ($q !== '') {
            $query->where(function (Builder $query) use ($q): void {
                $query->where('description', 'like', "%{$q}%")
                    ->orWhere('vendor_name', 'like', "%{$q}%")
                    ->orWhereHas('vehicle', function (Builder $vehicleQuery) use ($q): void {
                        $vehicleQuery->where('stock_number', 'like', "%{$q}%")
                            ->orWhere('license_plate', 'like', "%{$q}%")
                            ->orWhere('brand', 'like', "%{$q}%")
                            ->orWhere('model', 'like', "%{$q}%");
                    });
            });
        }

        $query
            ->when(Arr::get($filters, 'cost_type'), fn (Builder $query, string $costType) => $query->where('cost_type', $costType))
            ->when(Arr::get($filters, 'payment_status'), fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when(Arr::get($filters, 'date_from'), fn (Builder $query, string $date) => $query->whereDate('cost_date', '>=', $date))
            ->when(Arr::get($filters, 'date_to'), fn (Builder $query, string $date) => $query->whereDate('cost_date', '<=', $date));
    }

    /**
     * 技術註解：輸出採明確 allowlist，刻意不包含 company/branch/internal_notes/profit 等敏感或非本階段欄位。
     *
     * @return array<string, mixed>
     */
    private function serializeCost(VehicleCost $cost): array
    {
        return [
            'id' => $cost->id,
            'cost_type' => $cost->cost_type,
            'description' => $cost->description,
            'amount' => (string) $cost->amount,
            'cost_date' => optional($cost->cost_date)->format('Y-m-d'),
            'vendor' => $cost->vendor_name,
            'payment_status' => $cost->payment_status,
            'vehicle' => $cost->vehicle ? [
                'id' => $cost->vehicle->id,
                'stock_number' => $cost->vehicle->stock_number,
                'brand' => $cost->vehicle->brand,
                'model' => $cost->vehicle->model,
                'license_plate' => $cost->vehicle->license_plate,
                'lifecycle_status' => $cost->vehicle->lifecycle_status,
            ] : null,
            'creator_name' => $cost->creator?->name,
            'updater_name' => $cost->updater?->name,
            'created_at' => optional($cost->created_at)->toISOString(),
            'updated_at' => optional($cost->updated_at)->toISOString(),
        ];
    }

    /**
     * 技術註解：編輯頁 payload 採獨立 allowlist，允許 internal_notes 編輯但不輸出 tenant、actor 或利潤衍生欄位。
     *
     * @return array<string, mixed>
     */
    private function serializeEditableCost(VehicleCost $cost): array
    {
        return [
            'id' => $cost->id,
            'vehicle_id' => $cost->vehicle_id,
            'cost_type' => $cost->cost_type,
            'description' => $cost->description,
            'amount' => (string) $cost->amount,
            'cost_date' => optional($cost->cost_date)->format('Y-m-d'),
            'vendor_name' => $cost->vendor_name,
            'payment_status' => $cost->payment_status,
            'paid_at' => optional($cost->paid_at)->format('Y-m-d'),
            'internal_notes' => $cost->internal_notes,
            'vehicle' => $cost->vehicle ? [
                'id' => $cost->vehicle->id,
                'stock_number' => $cost->vehicle->stock_number,
                'brand' => $cost->vehicle->brand,
                'model' => $cost->vehicle->model,
                'license_plate' => $cost->vehicle->license_plate,
                'lifecycle_status' => $cost->vehicle->lifecycle_status,
            ] : null,
        ];
    }
}