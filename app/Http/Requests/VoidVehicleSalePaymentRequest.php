<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidVehicleSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->hasAny([
            'company_id', 'branch_id', 'vehicle_id', 'vehicle_sale_id', 'customer_id',
            'payment_number', 'payment_type', 'payment_method', 'amount', 'paid_at', 'reference_no',
            'status', 'created_by', 'updated_by', 'voided_by', 'voided_at',
            // 技術註解：作廢流程只允許 void_reason，拒絕 system/tenant 與財務衍生欄位以避免權限提升或毛利資料注入。
            'system', 'tenant', 'cost_amount', 'gross_profit', 'gross_margin', 'gross_margin_rate',
            'profit', 'profit_rate', 'purchase_cost', 'maintenance_cost',
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}