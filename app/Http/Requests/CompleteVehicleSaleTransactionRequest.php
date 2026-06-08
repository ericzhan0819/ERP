<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteVehicleSaleTransactionRequest extends FormRequest
{
    /**
     * 技術註解：completed_by / completed_at 必須由後端 action 決定，避免前端指定操作者或回填時間造成稽核失真。
     */
    public function authorize(): bool
    {
        // 技術註解：完成交易是敏感狀態轉換，任何 tenant、actor、會計、收入、COGS 或毛利欄位都視為越權 payload。
        return ! $this->hasAny([
            'company_id',
            'branch_id',
            'vehicle_id',
            'vehicle_sale_id',
            'customer_id',
            'completed_by',
            'completed_at',
            'created_by',
            'updated_by',
            'sale_status',
            'sold_at',
            'revenue',
            'revenue_amount',
            'revenue_recognition_status',
            'cogs',
            'cogs_amount',
            'cogs_recognition_status',
            'accounting_event_id',
            'journal_entry_id',
            'journal_entry_number',
            'cost_amount',
            'gross_profit',
            'gross_margin',
            'gross_margin_rate',
            'profit',
            'profit_rate',
            'purchase_cost',
            'maintenance_cost',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'completion_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
