<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleCostRequest extends FormRequest
{
    /**
     * 技術註解：更新授權由控制器在 tenant-scoped vehicle/cost 查詢後以 policy 判斷，避免 route 參數未隔離導致誤授權。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 技術註解：此 request 僅驗證成本業務欄位；tenant、vehicle 與 actor 欄位由 controller 在 scoped vehicle 後端寫入，避免前端覆寫資料邊界。
     * 因此 company_id、branch_id、vehicle_id、created_by、updated_by 不應進入 rules。
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cost_type' => ['required', 'string', Rule::in(array_keys(config('vehicles.vehicle_cost_types', [])))],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'cost_date' => ['required', 'date'],
            'vendor_name' => ['nullable', 'string', 'max:120'],
            'payment_status' => ['required', 'string', Rule::in(array_keys(config('vehicles.vehicle_cost_payment_statuses', [])))],
            'paid_at' => ['nullable', 'date'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
