<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleSaleRequest extends FormRequest
{
    /**
     * 技術註解：建立授權由控制器在 tenant-scoped vehicle 查詢後以 policy 判斷，避免 route 參數未隔離導致誤授權。
     */
    public function authorize(): bool
    {
        // 技術註解：系統欄位只能由後端決定；前端夾帶代表嘗試覆寫 tenant/actor 邊界，直接 403 降低權限提升風險。
        return ! $this->hasAny([
            'company_id',
            'branch_id',
            'vehicle_id',
            'created_by',
            'updated_by',
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
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_status' => ['required', 'string', Rule::in(array_keys(config('vehicle_sales.sale_statuses', [])))],
            'sold_at' => ['nullable', 'date'],
            'salesperson_name' => ['nullable', 'string', 'max:255'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * 技術註解：reserved/sold 需有成交價，避免車輛生命週期進入銷售狀態但缺少最基本銷售金額。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (in_array($this->input('sale_status'), ['reserved', 'sold'], true) && $this->input('sale_price') === null) {
                $validator->errors()->add('sale_price', '保留或成交狀態必須填寫銷售價格。');
            }
        });
    }
}
