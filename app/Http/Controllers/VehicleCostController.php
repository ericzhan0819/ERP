<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleCostRequest;
use App\Http\Requests\UpdateVehicleCostRequest;
use App\Models\Vehicle;
use App\Models\VehicleCost;
use App\Services\AuditLogService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class VehicleCostController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * 技術註解：建立流程先以 tenant-scoped 查 vehicle（404 優先），再授權與交易寫入，阻斷跨租戶成本注入。
     */
    public function store(StoreVehicleCostRequest $request, int $vehicle)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $foundVehicle = $this->scopedVehicleQuery($user)
            ->whereKey($vehicle)
            ->firstOrFail();

        $this->authorize('create', [VehicleCost::class, $foundVehicle]);

        $vehicleCost = DB::transaction(function () use ($request, $user, $foundVehicle): VehicleCost {
            $payload = [
                'company_id' => (int) $user->company_id,
                'branch_id' => (int) $foundVehicle->branch_id,
                'vehicle_id' => (int) $foundVehicle->id,
                'cost_type' => $request->validated('cost_type'),
                'description' => $request->validated('description'),
                'amount' => $request->validated('amount'),
                'cost_date' => $request->validated('cost_date'),
                'vendor_name' => $request->validated('vendor_name'),
                'payment_status' => $request->validated('payment_status'),
                'paid_at' => $request->validated('paid_at'),
                'internal_notes' => $request->validated('internal_notes'),
                'created_by' => (int) $user->id,
                'updated_by' => (int) $user->id,
            ];

            $created = VehicleCost::create($payload);

            $this->auditLogService->log(
                actor: $user,
                action: 'vehicle_cost.created',
                description: 'Vehicle cost created',
                targetUser: null,
                metadata: [],
                subject: $created,
                oldValues: null,
                newValues: $this->buildAuditableCostValues($created),
                request: $request,
                event: 'vehicle_cost.created',
            );

            return $created;
        });

        return redirect()->route('employee-system.vehicles.show', $vehicleCost->vehicle_id);
    }

    /**
     * 技術註解：更新流程先 scoped vehicle 再 scoped cost 且強制屬於該 vehicle，以 404 優先避免 IDOR 資訊探測。
     */
    public function update(UpdateVehicleCostRequest $request, int $vehicle, int $vehicleCost)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $foundVehicle = $this->scopedVehicleQuery($user)
            ->whereKey($vehicle)
            ->firstOrFail();

        $foundVehicleCost = $this->scopedVehicleCostQuery($user)
            ->where('vehicle_id', $foundVehicle->id)
            ->whereKey($vehicleCost)
            ->firstOrFail();

        $this->authorize('update', [$foundVehicleCost, $foundVehicle]);

        DB::transaction(function () use ($request, $user, $foundVehicle, $foundVehicleCost): void {
            $oldValues = $this->buildAuditableCostValues($foundVehicleCost);

            $foundVehicleCost->update([
                'company_id' => (int) $foundVehicle->company_id,
                'branch_id' => (int) $foundVehicle->branch_id,
                'vehicle_id' => (int) $foundVehicle->id,
                'cost_type' => $request->validated('cost_type'),
                'description' => $request->validated('description'),
                'amount' => $request->validated('amount'),
                'cost_date' => $request->validated('cost_date'),
                'vendor_name' => $request->validated('vendor_name'),
                'payment_status' => $request->validated('payment_status'),
                'paid_at' => $request->validated('paid_at'),
                'internal_notes' => $request->validated('internal_notes'),
                'updated_by' => (int) $user->id,
            ]);

            $this->auditLogService->log(
                actor: $user,
                action: 'vehicle_cost.updated',
                description: 'Vehicle cost updated',
                targetUser: null,
                metadata: [],
                subject: $foundVehicleCost,
                oldValues: $oldValues,
                newValues: $this->buildAuditableCostValues($foundVehicleCost->fresh()),
                request: $request,
                event: 'vehicle_cost.updated',
            );
        });

        return redirect()->route('employee-system.vehicles.show', $foundVehicle->id);
    }

    /**
     * 技術註解：集中可稽核欄位白名單，避免 internal_notes 與 tenant/system 欄位進入審計快照。
     *
     * @return array<string, mixed>
     */
    private function buildAuditableCostValues(VehicleCost $vehicleCost): array
    {
        return [
            'cost_type' => $vehicleCost->cost_type,
            'description' => $vehicleCost->description,
            'amount' => $vehicleCost->amount,
            'cost_date' => optional($vehicleCost->cost_date)->format('Y-m-d'),
            'vendor_name' => $vehicleCost->vendor_name,
            'payment_status' => $vehicleCost->payment_status,
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
     * 技術註解：集中成本 tenant 範圍，確保 update 查詢與 vehicle 範圍一致。
     */
    private function scopedVehicleCostQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $userCompanyId = (int) ($user?->company_id ?? 0);
        $userBranchId = $user?->branch_id;

        $query = VehicleCost::query()->where('company_id', $userCompanyId);

        if ($userBranchId !== null) {
            $query->where('branch_id', (int) $userBranchId);
        }

        return $query;
    }
}

