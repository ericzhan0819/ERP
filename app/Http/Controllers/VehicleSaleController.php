<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteVehicleSaleTransactionRequest;
use App\Http\Requests\StoreVehicleSaleRequest;
use App\Http\Requests\UpdateVehicleSaleRequest;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleSale;
use App\Services\AccountingEventService;
use App\Services\AuditLogService;
use App\Services\ReceivableSummaryService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class VehicleSaleController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ReceivableSummaryService $summaryService,
        private readonly AccountingEventService $accountingEventService,
    ) {}

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
            $validated = $request->validated();
            $customerSnapshot = $this->resolveCustomerSnapshot($user, $validated['customer_id'] ?? null, $validated);

            $created = VehicleSale::create([
                'company_id' => (int) $foundVehicle->company_id,
                'branch_id' => (int) $foundVehicle->branch_id,
                'vehicle_id' => (int) $foundVehicle->id,
                ...$customerSnapshot,
                'sale_price' => $validated['sale_price'] ?? null,
                'deposit_amount' => $validated['deposit_amount'] ?? null,
                'paid_amount' => $validated['paid_amount'] ?? null,
                'sale_status' => $validated['sale_status'],
                'sold_at' => $validated['sold_at'] ?? null,
                'salesperson_name' => $validated['salesperson_name'] ?? null,
                'commission_amount' => $validated['commission_amount'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => (int) $user->id,
                'updated_by' => (int) $user->id,
            ]);

            $this->syncVehicleLifecycleStatus($foundVehicle, $created->sale_status, null);

            $this->auditLogService->log(
                actor: $user,
                action: 'vehicle_sale.created',
                description: 'Vehicle sale created',
                targetUser: null,
                metadata: ['module' => 'vehicle_sales'],
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
            $validated = $request->validated();
            $customerSnapshot = $this->resolveCustomerSnapshot($user, $validated['customer_id'] ?? null, $validated);
            $oldValues = $this->buildAuditableSaleValues($foundVehicleSale);
            $oldSaleStatus = $foundVehicleSale->sale_status;

            $foundVehicleSale->update([
                'company_id' => (int) $foundVehicle->company_id,
                'branch_id' => (int) $foundVehicle->branch_id,
                'vehicle_id' => (int) $foundVehicle->id,
                ...$customerSnapshot,
                'sale_price' => $validated['sale_price'] ?? null,
                'deposit_amount' => $validated['deposit_amount'] ?? null,
                'paid_amount' => $validated['paid_amount'] ?? null,
                'sale_status' => $validated['sale_status'],
                'sold_at' => $validated['sold_at'] ?? null,
                'salesperson_name' => $validated['salesperson_name'] ?? null,
                'commission_amount' => $validated['commission_amount'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => (int) $user->id,
            ]);

            $this->syncVehicleLifecycleStatus($foundVehicle, $foundVehicleSale->sale_status, $oldSaleStatus);

            $this->auditLogService->log(
                actor: $user,
                action: 'vehicle_sale.updated',
                description: 'Vehicle sale updated',
                targetUser: null,
                metadata: ['module' => 'vehicle_sales'],
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
     * 技術註解：完成交易只寫入 completion 欄位並建立 pending Accounting Event 候選摘要，不自動 mark sold、認列收入、產生 COGS 或建立 journal draft。
     */
    public function complete(CompleteVehicleSaleTransactionRequest $request, int $vehicle, int $vehicleSale): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $foundVehicle = $this->scopedVehicleQuery($user)
            ->whereKey($vehicle)
            ->firstOrFail();

        $foundVehicleSale = $this->scopedVehicleSaleQuery($user)
            ->where('vehicle_id', $foundVehicle->id)
            ->whereKey($vehicleSale)
            ->with(['vehicle', 'payments', 'customer:id,customer_number,name,phone'])
            ->firstOrFail();

        $this->authorize('complete', [$foundVehicleSale, $foundVehicle]);

        $summary = $this->summaryService->summarize($foundVehicleSale);
        $this->ensureSaleCanBeCompleted($foundVehicleSale, $summary);

        DB::transaction(function () use ($request, $user, $foundVehicleSale, $summary): void {
            $oldValues = $this->buildCompletionAuditValues($foundVehicleSale, $summary);

            // 技術註解：completed_at / completed_by 由伺服器在交易內寫入，避免前端回填時間或指定操作者造成稽核失真。
            $foundVehicleSale->update([
                'completed_at' => now(),
                'completed_by' => (int) $user->id,
                'completion_note' => $request->validated('completion_note'),
                'updated_by' => (int) $user->id,
            ]);

            $foundVehicleSale->refresh()->load(['vehicle', 'payments', 'customer:id,customer_number,name,phone', 'completer:id,name']);
            $newSummary = $this->summaryService->summarize($foundVehicleSale);
            $this->accountingEventService->createVehicleSaleCompletedEvent($foundVehicleSale, $user);

            $this->auditLogService->log(
                actor: $user,
                action: 'vehicle_sale.transaction_completed',
                description: 'Vehicle sale transaction completed',
                targetUser: null,
                metadata: ['module' => 'vehicle_sales'],
                subject: $foundVehicleSale,
                oldValues: $oldValues,
                newValues: $this->buildCompletionAuditValues($foundVehicleSale, $newSummary),
                request: $request,
                event: 'vehicle_sale.transaction_completed',
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
     * 技術註解：交易完成必須由使用者明確觸發且通過狀態與收款檢查，避免收款或售出狀態自動推導完成。
     *
     * @param  array<string, mixed>  $summary
     */
    private function ensureSaleCanBeCompleted(VehicleSale $sale, array $summary): void
    {
        if ($sale->sale_status === 'cancelled') {
            throw new HttpResponseException(response()->json(['message' => '已取消銷售不可完成交易。'], 422));
        }

        if ($sale->vehicle?->lifecycle_status === 'archived') {
            throw new HttpResponseException(response()->json(['message' => '已封存車輛不可完成交易。'], 422));
        }

        if ($sale->completed_at !== null) {
            throw new HttpResponseException(response()->json(['message' => '此交易已完成，不可重複完成。'], 422));
        }

        if ($sale->sale_status !== 'sold') {
            throw new HttpResponseException(response()->json(['message' => '僅已成交銷售可完成交易。'], 422));
        }

        if ($sale->vehicle?->lifecycle_status !== 'sold') {
            throw new HttpResponseException(response()->json(['message' => '僅已售出車輛可完成交易。'], 422));
        }

        if ($sale->sale_price === null || (float) $sale->sale_price <= 0) {
            throw new HttpResponseException(response()->json(['message' => '銷售價格未設定，無法完成交易。'], 422));
        }

        if (! in_array($summary['receivable_status'] ?? null, ['paid', 'overpaid'], true)) {
            throw new HttpResponseException(response()->json(['message' => '收款尚未完成，無法完成交易。'], 422));
        }
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
            'customer_id' => $vehicleSale->customer_id,
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
     * 技術註解：completion audit 僅記錄安全白名單，避免 tenant raw ids、個資、會計分錄欄位或毛利資料進入稽核快照。
     *
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>
     */
    private function buildCompletionAuditValues(?VehicleSale $vehicleSale, ?array $summary = null): array
    {
        if ($vehicleSale === null) {
            return [];
        }

        return [
            'vehicle_sale_id' => $vehicleSale->id,
            'vehicle_id' => $vehicleSale->vehicle?->id ?? $vehicleSale->vehicle_id,
            'vehicle_stock_number' => $vehicleSale->vehicle?->stock_number,
            'customer_id' => $vehicleSale->customer_id,
            'customer_number' => $vehicleSale->customer?->customer_number,
            'customer_name' => $vehicleSale->customer?->name ?? $vehicleSale->customer_name,
            'sale_status' => $vehicleSale->sale_status,
            'sold_at' => optional($vehicleSale->sold_at)->format('Y-m-d H:i:s'),
            'completed_at' => optional($vehicleSale->completed_at)->format('Y-m-d H:i:s'),
            'completed_by' => $vehicleSale->completed_by,
            'completion_note' => $vehicleSale->completion_note,
            'receivable_status' => $summary['receivable_status'] ?? null,
            'receivable_amount' => $summary['receivable_amount'] ?? null,
            'received_amount' => $summary['received_amount'] ?? null,
            'receivable_balance' => $summary['receivable_balance'] ?? null,
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
     * 技術註解：Customer 主檔查詢必須套用與登入者相同 tenant 邊界，避免以 customer_id 探測或綁定他租戶個資。
     */
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

    /**
     * 技術註解：選擇 customer_id 時一律由 Customer 主檔覆寫 snapshot，防止前端竄改成交客戶姓名或電話。
     *
     * @param  array<string, mixed>  $validated
     * @return array{customer_id: int|null, customer_name: string|null, customer_phone: string|null}
     */
    private function resolveCustomerSnapshot(?Authenticatable $user, ?int $customerId, array $validated): array
    {
        if ($customerId === null) {
            return [
                'customer_id' => null,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
            ];
        }

        $customer = $this->scopedCustomerQuery($user)
            ->whereKey($customerId)
            ->firstOrFail(['id', 'name', 'phone']);

        return [
            'customer_id' => (int) $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
        ];
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
