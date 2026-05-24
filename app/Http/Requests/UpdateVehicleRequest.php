<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    /**
     * 技術註解：更新授權需依 scoped 後實體判斷，避免先用 route 參數做授權導致跨租戶誤判。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'vin' => ['nullable', 'string', 'max:30'],
            'license_plate' => ['nullable', 'string', 'max:30'],
            'brand' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:80'],
            'variant' => ['nullable', 'string', 'max:80'],
            'model_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'exterior_color' => ['nullable', 'string', 'max:50'],
            'interior_color' => ['nullable', 'string', 'max:50'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'lifecycle_status' => ['required', 'string', Rule::in(array_keys(config('vehicles.lifecycle_statuses')))],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
