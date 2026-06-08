<?php

namespace App\Services;

use App\Models\AccountingEvent;
use App\Models\User;
use App\Models\VehicleSale;

class AccountingEventService
{
    public function __construct(
        private readonly ReceivableSummaryService $receivableSummaryService,
    ) {}

    /**
     * 技術註解：Completion 只建立待覆核候選事件，嚴格不產生 journal draft、分錄、收入認列、COGS 或毛利資料。
     */
    public function createVehicleSaleCompletedEvent(VehicleSale $sale, User $actor): AccountingEvent
    {
        $existing = AccountingEvent::query()
            ->where('company_id', (int) $sale->company_id)
            ->where('source_type', 'vehicle_sale_completion')
            ->where('source_id', (int) $sale->id)
            ->where('event_type', 'vehicle_sale_completed')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $sale->loadMissing(['vehicle', 'customer', 'completer', 'payments']);

        $sourceNumber = $sale->vehicle?->stock_number ?: 'SALE-'.$sale->id;
        $summary = $this->receivableSummaryService->summarize($sale);

        return AccountingEvent::create([
            'company_id' => (int) $sale->company_id,
            'branch_id' => $sale->branch_id === null ? null : (int) $sale->branch_id,
            'source_type' => 'vehicle_sale_completion',
            'source_id' => (int) $sale->id,
            'source_number' => $sourceNumber,
            'event_type' => 'vehicle_sale_completed',
            'event_date' => $sale->completed_at?->toDateString() ?? now()->toDateString(),
            'status' => 'pending',
            'currency' => 'TWD',
            'amount' => $sale->sale_price,
            'payload' => $this->buildVehicleSaleCompletedPayload($sale, $summary, $sourceNumber),
            'created_by' => (int) $actor->id,
        ]);
    }

    /**
     * 技術註解：payload 採後端安全白名單，避免 request 個資、租戶 raw ids、會計分錄欄位或 profit/gross margin 進入候選事件。
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function buildVehicleSaleCompletedPayload(VehicleSale $sale, array $summary, string $sourceNumber): array
    {
        return [
            'vehicle_sale_id' => (int) $sale->id,
            'vehicle_id' => $sale->vehicle_id === null ? null : (int) $sale->vehicle_id,
            'vehicle_stock_number' => $sale->vehicle?->stock_number,
            'vehicle_label' => trim(implode(' ', array_filter([
                $sale->vehicle?->brand,
                $sale->vehicle?->model,
                $sale->vehicle?->variant,
                $sale->vehicle?->model_year,
            ], fn ($value): bool => $value !== null && $value !== ''))) ?: null,
            'customer_id' => $sale->customer_id === null ? null : (int) $sale->customer_id,
            'customer_number' => $sale->customer?->customer_number,
            'customer_name' => $sale->customer?->name ?? $sale->customer_name,
            'sale_status' => $sale->sale_status,
            'sold_at' => $sale->sold_at?->format('Y-m-d H:i:s'),
            'completed_at' => $sale->completed_at?->format('Y-m-d H:i:s'),
            'completed_by_name' => $sale->completer?->name,
            'receivable_status' => $summary['receivable_status'] ?? null,
            'receivable_status_label' => $summary['receivable_status_label'] ?? null,
            'receivable_amount' => $summary['receivable_amount'] ?? null,
            'received_amount' => $summary['received_amount'] ?? null,
            'receivable_balance' => $summary['receivable_balance'] ?? null,
            'source_number' => $sourceNumber,
        ];
    }
}
