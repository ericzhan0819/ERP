<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->hasAny([
            'company_id', 'branch_id', 'vehicle_id', 'vehicle_sale_id', 'customer_id',
            'payment_number', 'status', 'created_by', 'updated_by', 'voided_by', 'voided_at',
            // 技術註解：拒絕泛用 system/tenant 容器欄位，避免前端以巢狀 payload 嘗試覆寫租戶或系統狀態。
            'system', 'tenant',
            'cost_amount', 'gross_profit', 'gross_margin', 'gross_margin_rate', 'profit', 'profit_rate',
            'purchase_cost', 'maintenance_cost',
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'payment_type' => ['required', 'string', Rule::in(array_keys(config('vehicle_sale_payments.payment_types', [])))],
            'payment_method' => ['required', 'string', Rule::in(array_keys(config('vehicle_sale_payments.payment_methods', [])))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['nullable', 'date'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}