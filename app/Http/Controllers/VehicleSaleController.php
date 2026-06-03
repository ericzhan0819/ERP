<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleSaleRequest;
use App\Http\Requests\UpdateVehicleSaleRequest;
use App\Models\Vehicle;
use App\Models\VehicleSale;
use App\Services\AuditLogService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class VehicleSaleController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * 技術註解：建立流程先 tenant-scoped 查車輛（404 優先），再 policy 授權與交易寫入，避免跨租戶銷售注入與 IDOR。
     */
    public function store(StoreVehicleSaleRequest $request, int $vehicle)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $foundVehicle = $this->scopedVehicleQuery($user)
            ->whereKey($vehicle)
            ->firstOrFail();

        $this->authorize('create', [VehicleSale::class, $foundVehicle]);

        $this->ensureNoActiveSaleConflict($foundVehicle, $request->validated('sale_status'));

        $vehicleSale = DB::transaction(function () use ($request, $user, $foundVehicle): VehicleSale {
            $created = VehicleSale::create([
                'company_id' => (int) $foundVehicle->company_id,
                'branch_id' => (int) $foundVehicle->branch_id,
                'vehicle_id' => (int) $foundVehicle->id,
                'customer_name' => $request->validated('customer_name'),
                'customer_phone' => $request->validated('customer_phone'),
                'sale_price' => $request->validated('sale_price'),
                'deposit_amount' => $request->validated('deposit_amount'),
                'paid_amount' => $request->validated('paid_amount'),
                'sale_status' => $request->validated('sale_status'),
                'sold_at' => $request->validated('sold_at'),
                'salesperson_name' => $request->validated('salesperson_name'),
                'commission_amount' => $request->validated('commission_amount'),
                'notes' => $request->validated('notes'),
                'created_by' => (int) $user->id,
                'updated_by' => (int) $user->id,
            ]);

            $this->syncVehicleLifecycleStatus($foundVehicle, $created->sale_status, null);

            $this->auditLogService->log(
                actor: $user,
                action: 'vehicle_sale.created',
                description: 'Vehicle sale created',
                targetUser: null,
                metadata: [],
                subject: $created,
                oldValues: null,
                newValues: $this->buildAuditableSaleValues($created),
                request: $request,
                event: 'vehicle_sale.created',
            );

            return $created;
        });

        return redirect()->route('employee-system.vehicles.show', $vehicleSale->vehicle_id);
    }

    /**
     * 技術註解：更新流程同時 scoped vehicle 與 scoped sale 且強制 sale 屬於 route vehicle，查無資料 404 優先阻斷 IDOR 探測。
     */
    public function update(UpdateVehicleSaleRequest $request, int $vehicle, int $vehicleSale)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $foundVehicle = $this->scopedVehicleQuery($user)
            ->whereKey($vehicle)
            ->firstOrFail();

        $foundVehicleSale = $this->scopedVehicleSaleQuery($user)
            ->where('vehicle_id', $foundVehicle->id)
            ->whereKey($vehicleSale)
            ->firstOrFail();

        $this->authorize('update', [$foundVehicleSale, $foundVehicle]);

        $this->ensureSoldSaleIsNotReservedAgain($foundVehicleSale, $request->validated('sale_status'));
        $this->ensureNoActiveSaleConflict($foundVehicle, $request->validated('sale_status'), $foundVehicleSale->id);

        DB::transaction(function () use ($request, $user, $foundVehicle, $foundVehicleSale): void {
            $oldValues = $this->buildAuditableSaleValues($foundVehicleSale);
            $oldSaleStatus = $foundVehicleSale->sale_status;

            $foundVehicleSale->update([
                'company_id' => (int) $foundVehicle->company_id,
                'branch_id' => (int) $foundVehicle->branch_id,
                'vehicle_id' => (int) $foundVehicle->id,
                'customer_name' => $request->validated('customer_name'),
                'customer_phone' => $request->validated('customer_phone'),
                'sale_price' => $request->validated('sale_price'),
                'deposit_amount' => $request->validated('deposit_amount'),
                'paid_amount' => $request->validated('paid_amount'),
                'sale_status' => $request->validated('sale_status'),
                'sold_at' => $request->validated('sold_at'),
                'salesperson_name' => $request->validated('salesperson_name'),
                'commission_amount' => $request->validated('commission_amount'),
                'notes' => $request->validated('notes'),
                'updated_by' => (int) $user->id,
            ]);

            $this->syncVehicleLifecycleStatus($foundVehicle, $foundVehicleSale->sale_status, $oldSaleStatus);

            $this->auditLogService->log(
                actor: $user,
                action: 'vehicle_sale.updated',
                description: 'Vehicle sale updated',
                targetUser: null,
                metadata: [],
                subject: $foundVehicleSale,
                oldValues: $oldValues,
                newValues: $this->buildAuditableSaleValues($foundVehicleSale->fresh()),
                request: $request,
                event: 'vehicle_sale.updated',
            );
        });

        return redirect()->route('employee-system.vehicles.show', $foundVehicle->id);
    }

    /**
     * 技術註解：每車僅允許一筆 active sale，避免多筆保留/成交互相覆寫 vehicle lifecycle_status。
     */
    private function ensureNoActiveSaleConflict(Vehicle $vehicle, string $nextStatus, ?int $ignoreSaleId = null): void
    {
        if ($nextStatus === 'cancelled') {
            return;
        }

        if ($vehicle->lifecycle_status === 'sold') {
            throw new HttpResponseException(response()->json([
                'message' => '已成交車輛不可建立新的銷售紀錄。',
            ], 422));
        }

        $exists = $vehicle->sales()
            ->whereIn('sale_status', ['draft', 'reserved', 'sold'])
            ->when($ignoreSaleId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreSaleId))
            ->exists();

        if ($exists) {
            throw new HttpResponseException(response()->json([
                'message' => '此車已有進行中的銷售紀錄。',
            ], 422));
        }
    }

    /**
     * 技術註解：已成交紀錄不可退回保留，避免用簡化狀態切換繞過後續退車/退款/作廢流程。
     */
    private function ensureSoldSaleIsNotReservedAgain(VehicleSale $vehicleSale, string $nextStatus): void
    {
        if ($vehicleSale->sale_status !== 'sold' || $nextStatus !== 'reserved') {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => '已成交銷售紀錄不可改回保留狀態。',
        ], 422));
    }

    /**
     * 技術註解：生命週期同步僅處理 MVP 明確狀態；取消已成交銷售不自動回 in_stock，避免把已售車錯誤回庫存。
     */
    private function syncVehicleLifecycleStatus(Vehicle $vehicle, string $saleStatus, ?string $oldSaleStatus): void
    {
        $nextLifecycleStatus = match ($saleStatus) {
            'reserved' => 'reserved',
            'sold' => 'sold',
            'cancelled' => $oldSaleStatus === 'sold' || $vehicle->lifecycle_status === 'sold' ? null : 'in_stock',
            default => null,
        };

        if ($nextLifecycleStatus === null) {
            return;
        }

        $vehicle->forceFill([
            'lifecycle_status' => $nextLifecycleStatus,
        ])->save();
    }

    /**
     * 技術註解：集中可稽核欄位白名單，避免 tenant/actor 欄位與未授權毛利資料進入審計快照。
     *
     * @return array<string, mixed>
     */
    private function buildAuditableSaleValues(?VehicleSale $vehicleSale): array
    {
        if ($vehicleSale === null) {
            return [];
        }

        return [
            'customer_name' => $vehicleSale->customer_name,
            'customer_phone' => $vehicleSale->customer_phone,
            'sale_price' => $vehicleSale->sale_price,
            'deposit_amount' => $vehicleSale->deposit_amount,
            'paid_amount' => $vehicleSale->paid_amount,
            'sale_status' => $vehicleSale->sale_status,
            'sold_at' => optional($vehicleSale->sold_at)->format('Y-m-d H:i:s'),
            'salesperson_name' => $vehicleSale->salesperson_name,
            'commission_amount' => $vehicleSale->commission_amount,
            'notes' => $vehicleSale->notes,
        ];
    }

    /**
     * 技術註解：集中 vehicle tenant 範圍，先 company 再 branch，避免跨租戶讀取。
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

    /**
     * 技術註解：集中銷售 tenant 範圍，確保 update 查詢與 vehicle 範圍一致。
     */
    private function scopedVehicleSaleQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $userCompanyId = (int) ($user?->company_id ?? 0);
        $userBranchId = $user?->branch_id;

        $query = VehicleSale::query()->where('company_id', $userCompanyId);

        if ($userBranchId !== null) {
            $query->where('branch_id', (int) $userBranchId);
        }

        return $query;
    }
}
