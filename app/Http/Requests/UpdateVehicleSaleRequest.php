<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVehicleSaleRequest extends FormRequest
{
    /**
     * 技術註解：更新授權由控制器在 tenant-scoped vehicle/sale 查詢後以 policy 判斷，避免未隔離 route 參數造成 IDOR。
     */
    public function authorize(): bool
    {
        // 技術註解：更新時同樣禁止前端覆寫 tenant/actor/vehicle 欄位，避免跨租戶或跨車銷售污染。
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
            'id_number',
            'birthday',
            'address',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
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
     * 技術註解：reserved/sold 需有成交價，確保生命週期同步後仍保有最小銷售資料完整性。
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
