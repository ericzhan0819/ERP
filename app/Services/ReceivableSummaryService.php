<?php

namespace App\Services;

use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;

class ReceivableSummaryService
{
    /**
     * 技術註解：應收狀態只以 vehicle_sale_payments 的 received 紀錄計算，避免誤用 vehicle_sales.paid_amount 舊快照欄位造成財務語意錯誤。
     *
     * @return array<string, mixed>
     */
    public function summarize(VehicleSale $sale): array
    {
        $payments = $sale->relationLoaded('payments') ? $sale->payments : $sale->payments()->get();
        $receivedPayments = $payments->where('status', 'received');
        $receivableAmount = $sale->sale_price === null ? null : (float) $sale->sale_price;
        $receivedAmount = (float) $receivedPayments->sum(fn (VehicleSalePayment $payment): float => (float) $payment->amount);
        $balance = $receivableAmount === null ? null : $receivableAmount - $receivedAmount;
        $status = $this->resolveStatus($receivableAmount, $receivedAmount);
        $labels = config('vehicle_sale_payments.receivable_statuses', []);

        return [
            'receivable_amount' => $receivableAmount === null ? null : number_format($receivableAmount, 2, '.', ''),
            'received_amount' => number_format($receivedAmount, 2, '.', ''),
            'receivable_balance' => $balance === null ? null : number_format($balance, 2, '.', ''),
            'receivable_status' => $status,
            'receivable_status_label' => $labels[$status] ?? $status,
            'received_payment_count' => $receivedPayments->count(),
            'payment_record_count' => $payments->count(),
            'latest_payment_paid_at' => optional($receivedPayments->sortByDesc('paid_at')->first()?->paid_at)->format('Y-m-d'),
        ];
    }

    private function resolveStatus(?float $receivableAmount, float $receivedAmount): string
    {
        $receivableAmount ??= 0.0;

        if ($receivedAmount <= 0) {
            return 'unpaid';
        }

        if ($receivedAmount < $receivableAmount) {
            return 'partial';
        }

        if ($receivedAmount == $receivableAmount) {
            return 'paid';
        }

        return 'overpaid';
    }
}