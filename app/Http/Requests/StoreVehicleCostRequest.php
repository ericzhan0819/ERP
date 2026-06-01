<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleCostRequest extends FormRequest
{
    /**
     * 技術註解：建立授權由控制器在 tenant-scoped vehicle 查詢後以 policy 統一判斷，避免先用未隔離參數授權。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
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

