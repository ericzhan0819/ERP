<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleSalePaymentRequest;
use App\Http\Requests\VoidVehicleSalePaymentRequest;
use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;
use App\Services\AuditLogService;
use App\Services\ReceivableSummaryService;
use App\Services\VehicleSalePaymentNumberService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableController extends Controller
{
    public function __construct(
        private readonly VehicleSalePaymentNumberService $paymentNumberService,
        private readonly AuditLogService $auditLogService,
        private readonly ReceivableSummaryService $summaryService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('module.receivables.view'), 403);

        $q = trim((string) $request->query('q', ''));
        $receivableStatus = (string) $request->query('receivable_status', 'all');
        $saleStatus = (string) $request->query('sale_status', 'all');
        $allowedReceivableStatuses = ['all', 'unpaid', 'partial', 'paid', 'overpaid'];
        $allowedSaleStatuses = ['all', 'reserved', 'sold'];
        $receivableStatus = in_array($receivableStatus, $allowedReceivableStatuses, true) ? $receivableStatus : 'all';
        $saleStatus = in_array($saleStatus, $allowedSaleStatuses, true) ? $saleStatus : 'all';

        $sales = $this->scopedVehicleSaleQuery($request->user())
            ->with(['vehicle:id,stock_number,vin,license_plate,brand,model', 'customer:id,customer_number,name,phone', 'payments'])
            // 技術註解：收款管理 MVP 主要處理 reserved/sold，排除 draft/cancelled 以避免把未成立或已取消交易誤列為待收款清單。
            ->whereIn('sale_status', ['reserved', 'sold'])
            ->when($saleStatus !== 'all', fn (Builder $query) => $query->where('sale_status', $saleStatus))
            ->when($q !== '', function (Builder $query) use ($q): void {
                $query->where(function (Builder $subQuery) use ($q): void {
                    $subQuery->where('customer_name', 'like', "%{$q}%")
                        ->orWhere('customer_phone', 'like', "%{$q}%")
                        ->orWhereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery
                            ->where('stock_number', 'like', "%{$q}%")
                            ->orWhere('vin', 'like', "%{$q}%")
                            ->orWhere('license_plate', 'like', "%{$q}%")
                            ->orWhere('brand', 'like', "%{$q}%")
                            ->orWhere('model', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (VehicleSale $sale): array => $this->buildSaleListPayload($sale))
            ->withQueryString();

        if ($receivableStatus !== 'all') {
            $sales->setCollection($sales->getCollection()->filter(fn (array $sale): bool => ($sale['payment_summary']['receivable_status'] ?? null) === $receivableStatus)->values());
        }

        return Inertia::render('Receivables/Index', [
            'sales' => $sales,
            'filters' => ['q' => $q, 'receivable_status' => $receivableStatus, 'sale_status' => $saleStatus],
            'receivableStatuses' => config('vehicle_sale_payments.receivable_statuses', []),
            'saleStatuses' => config('vehicle_sales.sale_statuses', []),
        ]);
    }

    public function show(Request $request, int $vehicleSale): Response
    {
        abort_unless($request->user()?->can('module.receivables.view'), 403);

        $sale = $this->scopedVehicleSaleQuery($request->user())
            ->with(['vehicle:id,stock_number,vin,license_plate,brand,model,lifecycle_status', 'customer:id,customer_number,name,phone', 'payments.creator:id,name', 'payments.voider:id,name'])
            ->whereKey($vehicleSale)
            ->firstOrFail();

        return Inertia::render('Receivables/Show', [
            'sale' => $this->buildSaleDetailPayload($sale),
            'paymentTypes' => config('vehicle_sale_payments.payment_types', []),
            'paymentMethods' => config('vehicle_sale_payments.payment_methods', []),
            'can' => [
                'create_receivables' => $request->user()?->can('module.receivables.create') ?? false,
                'void_receivables' => $request->user()?->can('module.receivables.void') ?? false,
                'can_mark_sold_receivable' => $request->user()?->can('module.receivables.mark-sold') ?? false,
            ],
        ]);
    }

    public function markSold(Request $request, int $vehicleSale): RedirectResponse
    {
        abort_unless($request->user()?->can('module.receivables.mark-sold'), 403);

        $sale = $this->scopedVehicleSaleQuery($request->user())
            ->with(['vehicle', 'payments'])
            ->whereKey($vehicleSale)
            ->firstOrFail();

        $summary = $this->summaryService->summarize($sale);
        $this->ensureSaleCanBeMarkedSold($sale, $summary);

        DB::transaction(function () use ($request, $sale, $summary): void {
            $vehicle = $sale->vehicle;
            $oldValues = $this->buildMarkedSoldOldAuditValues($sale);
            $soldAt = $sale->sold_at ?? now()->startOfDay();

            // 技術註解：此動作只在使用者明確點擊後執行，避免新增/作廢收款時自動改變銷售與車輛狀態。
            $sale->update([
                'sale_status' => 'sold',
                'sold_at' => $soldAt,
                'updated_by' => (int) $request->user()->id,
            ]);

            // 技術註解：車輛狀態與銷售狀態同交易更新，避免成交銷售與車輛生命週期不一致。
            $vehicle->update([
                'lifecycle_status' => 'sold',
                'updated_by' => (int) $request->user()->id,
            ]);

            $sale->refresh()->load('vehicle');
            $this->auditLogService->log($request->user(), 'vehicle_sale.marked_sold_from_receivable', 'Vehicle sale marked sold from receivable', null, ['module' => 'receivables'], $sale, $oldValues, $this->buildMarkedSoldNewAuditValues($sale, $summary), $request, 'vehicle_sale.marked_sold_from_receivable');
        });

        return redirect()->route('employee-system.receivables.show', $sale->id);
    }

    public function storePayment(StoreVehicleSalePaymentRequest $request, int $vehicleSale): RedirectResponse
    {
        abort_unless($request->user()?->can('module.receivables.create'), 403);
        $sale = $this->scopedVehicleSaleQuery($request->user())->with('vehicle')->whereKey($vehicleSale)->firstOrFail();
        $this->ensureSaleCanReceivePayment($sale);

        DB::transaction(function () use ($request, $sale): void {
            $validated = $request->validated();
            $payment = VehicleSalePayment::create([
                'company_id' => (int) $sale->company_id,
                'branch_id' => (int) $sale->branch_id,
                'vehicle_id' => (int) $sale->vehicle_id,
                'vehicle_sale_id' => (int) $sale->id,
                'customer_id' => $sale->customer_id,
                'payment_number' => $this->paymentNumberService->generate((int) $sale->company_id),
                'payment_type' => $validated['payment_type'],
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'paid_at' => $validated['paid_at'] ?? null,
                'reference_no' => $validated['reference_no'] ?? null,
                'status' => 'received',
                'notes' => $validated['notes'] ?? null,
                'created_by' => (int) $request->user()->id,
                'updated_by' => (int) $request->user()->id,
            ]);

            $this->auditLogService->log($request->user(), 'vehicle_sale_payment.created', 'Vehicle sale payment created', null, ['module' => 'receivables'], $payment, null, $this->buildCreatedAuditValues($payment), $request, 'vehicle_sale_payment.created');
        });

        return redirect()->route('employee-system.receivables.show', $sale->id);
    }

    public function voidPayment(VoidVehicleSalePaymentRequest $request, int $vehicleSale, int $vehicleSalePayment): RedirectResponse
    {
        abort_unless($request->user()?->can('module.receivables.void'), 403);
        $sale = $this->scopedVehicleSaleQuery($request->user())->whereKey($vehicleSale)->firstOrFail();
        $payment = $this->scopedVehicleSalePaymentQuery($request->user())->where('vehicle_sale_id', $sale->id)->whereKey($vehicleSalePayment)->firstOrFail();

        if ($payment->status !== 'received') {
            throw new HttpResponseException(response()->json(['message' => '僅可作廢已收款紀錄。'], 422));
        }

        DB::transaction(function () use ($request, $payment): void {
            $oldValues = $this->buildVoidedAuditValues($payment);
            $payment->update(['status' => 'voided', 'voided_by' => (int) $request->user()->id, 'voided_at' => now(), 'void_reason' => $request->validated('void_reason'), 'updated_by' => (int) $request->user()->id]);
            $this->auditLogService->log($request->user(), 'vehicle_sale_payment.voided', 'Vehicle sale payment voided', null, ['module' => 'receivables'], $payment, $oldValues, $this->buildVoidedAuditValues($payment->fresh()), $request, 'vehicle_sale_payment.voided');
        });

        return redirect()->route('employee-system.receivables.show', $sale->id);
    }

    private function scopedVehicleSaleQuery(?Authenticatable $user): Builder
    {
        $query = VehicleSale::query()->where('company_id', (int) ($user?->company_id ?? 0));
        if ($user?->branch_id !== null) { $query->where('branch_id', (int) $user->branch_id); }
        return $query;
    }

    private function scopedVehicleSalePaymentQuery(?Authenticatable $user): Builder
    {
        $query = VehicleSalePayment::query()->where('company_id', (int) ($user?->company_id ?? 0));
        if ($user?->branch_id !== null) { $query->where('branch_id', (int) $user->branch_id); }
        return $query;
    }

    private function ensureSaleCanReceivePayment(VehicleSale $sale): void
    {
        if ($sale->sale_status === 'cancelled') throw new HttpResponseException(response()->json(['message' => '已取消銷售不可新增收款紀錄。'], 422));
        if ($sale->sale_price === null || (float) $sale->sale_price <= 0) throw new HttpResponseException(response()->json(['message' => '銷售價格未設定，無法新增收款。'], 422));
    }

    /** @param array<string, mixed> $summary */
    private function ensureSaleCanBeMarkedSold(VehicleSale $sale, array $summary): void
    {
        if ($sale->sale_status === 'cancelled') throw new HttpResponseException(response()->json(['message' => '已取消銷售不可標記成交。'], 422));
        if ($sale->vehicle?->lifecycle_status === 'archived') throw new HttpResponseException(response()->json(['message' => '已封存車輛不可標記成交。'], 422));
        if ($sale->sale_status !== 'reserved') throw new HttpResponseException(response()->json(['message' => '只有保留中的銷售可標記成交。'], 422));
        if ($sale->vehicle?->lifecycle_status !== 'reserved') throw new HttpResponseException(response()->json(['message' => '只有保留中的車輛可標記成交。'], 422));
        if ($sale->sale_price === null || (float) $sale->sale_price <= 0) throw new HttpResponseException(response()->json(['message' => '銷售價格未設定，無法標記成交。'], 422));
        if (!in_array($summary['receivable_status'] ?? null, ['paid', 'overpaid'], true)) throw new HttpResponseException(response()->json(['message' => '收款尚未完成，無法標記成交。'], 422));
    }

    /** @param array<string, mixed> $summary */
    private function resolveMarkSoldHelpText(VehicleSale $sale, array $summary): ?string
    {
        if ($sale->sale_status === 'cancelled') return '已取消銷售不可標記成交。';
        if ($sale->vehicle?->lifecycle_status === 'archived') return '已封存車輛不可標記成交。';
        if ($sale->sale_status !== 'reserved') return '只有保留中的銷售可標記成交。';
        if ($sale->vehicle?->lifecycle_status !== 'reserved') return '只有保留中的車輛可標記成交。';
        if ($sale->sale_price === null || (float) $sale->sale_price <= 0) return '銷售價格未設定，無法標記成交。';
        if (!in_array($summary['receivable_status'] ?? null, ['paid', 'overpaid'], true)) return '收款尚未完成，無法標記成交。';
        return null;
    }

    /** @return array<string, mixed> */
    private function buildSaleListPayload(VehicleSale $sale): array
    {
        $summary = $this->summaryService->summarize($sale);
        return [
            'id' => $sale->id,
            'sale_status' => $sale->sale_status,
            'sale_status_label' => config('vehicle_sales.sale_statuses.'.$sale->sale_status, $sale->sale_status),
            'sale_price' => $sale->sale_price,
            // 技術註解：收款 payload 僅輸出畫面必要車輛欄位，不回傳 company_id/branch_id 以降低 tenant 邊界資訊外洩。
            'vehicle' => $sale->vehicle ? [
                'id' => $sale->vehicle->id,
                'stock_number' => $sale->vehicle->stock_number,
                'vin' => $sale->vehicle->vin,
                'license_plate' => $sale->vehicle->license_plate,
                'brand' => $sale->vehicle->brand,
                'model' => $sale->vehicle->model,
                'lifecycle_status' => $sale->vehicle->lifecycle_status,
            ] : null,
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'customer_number' => $sale->customer->customer_number,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
            ] : null,
            'customer_name' => $sale->customer_name,
            'customer_phone' => $sale->customer_phone,
            'payment_summary' => $summary,
        ];
    }

    /** @return array<string, mixed> */
    private function buildSaleDetailPayload(VehicleSale $sale): array
    {
        $paymentTypes = config('vehicle_sale_payments.payment_types', []); $paymentMethods = config('vehicle_sale_payments.payment_methods', []); $paymentStatuses = config('vehicle_sale_payments.statuses', []);
        $payload = $this->buildSaleListPayload($sale);
        $markSoldHelpText = $this->resolveMarkSoldHelpText($sale, $payload['payment_summary']);
        return $payload + ['sold_at' => optional($sale->sold_at)->format('Y-m-d'), 'salesperson_name' => $sale->salesperson_name, 'notes' => $sale->notes, 'receivable_block_reason' => $sale->sale_status === 'cancelled' ? '已取消銷售不可新增收款紀錄。' : (($sale->sale_price === null || (float) $sale->sale_price <= 0) ? '銷售價格未設定，無法新增收款。' : null), 'canMarkSold' => $markSoldHelpText === null, 'markSoldHelpText' => $markSoldHelpText, 'payments' => $sale->payments->sortByDesc('id')->map(fn (VehicleSalePayment $payment): array => ['id' => $payment->id, 'payment_number' => $payment->payment_number, 'payment_type_label' => $paymentTypes[$payment->payment_type] ?? $payment->payment_type, 'payment_method_label' => $paymentMethods[$payment->payment_method] ?? $payment->payment_method, 'amount' => $payment->amount, 'paid_at' => optional($payment->paid_at)->format('Y-m-d'), 'reference_no' => $payment->reference_no, 'status' => $payment->status, 'status_label' => $paymentStatuses[$payment->status] ?? $payment->status, 'notes' => $payment->notes, 'creator' => $payment->creator ? ['name' => $payment->creator->name] : null, 'voider' => $payment->voider ? ['name' => $payment->voider->name] : null, 'voided_at' => optional($payment->voided_at)->format('Y-m-d H:i:s'), 'void_reason' => $payment->void_reason])->values()];
    }

    private function buildCreatedAuditValues(VehicleSalePayment $payment): array { return ['payment_number' => $payment->payment_number, 'vehicle_sale_id' => $payment->vehicle_sale_id, 'customer_id' => $payment->customer_id, 'payment_type' => $payment->payment_type, 'payment_method' => $payment->payment_method, 'amount' => $payment->amount, 'paid_at' => optional($payment->paid_at)->format('Y-m-d'), 'reference_no' => $payment->reference_no, 'status' => $payment->status, 'notes' => $payment->notes]; }
    private function buildVoidedAuditValues(?VehicleSalePayment $payment): array { return ['payment_number' => $payment?->payment_number, 'status' => $payment?->status, 'voided_at' => optional($payment?->voided_at)->format('Y-m-d H:i:s'), 'void_reason' => $payment?->void_reason]; }
    private function buildMarkedSoldOldAuditValues(VehicleSale $sale): array { return ['sale_status' => $sale->sale_status, 'vehicle_lifecycle_status' => $sale->vehicle?->lifecycle_status, 'sold_at' => optional($sale->sold_at)->format('Y-m-d')]; }
    /** @param array<string, mixed> $summary */
    private function buildMarkedSoldNewAuditValues(VehicleSale $sale, array $summary): array { return ['sale_status' => $sale->sale_status, 'vehicle_lifecycle_status' => $sale->vehicle?->lifecycle_status, 'sold_at' => optional($sale->sold_at)->format('Y-m-d'), 'receivable_status' => $summary['receivable_status'] ?? null, 'received_amount' => $summary['received_amount'] ?? null, 'receivable_amount' => $summary['receivable_amount'] ?? null]; }
}