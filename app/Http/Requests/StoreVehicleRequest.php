<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    /**
     * 技術註解：建立車輛前先走 Policy create，避免未授權使用者送出寫入請求造成權限繞過風險。
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Vehicle::class);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'stock_number' => ['required', 'string', 'max:50'],
            'vin' => ['nullable', 'string', 'max:30'],
            'license_plate' => ['nullable', 'string', 'max:30'],
            'brand' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:80'],
            'variant' => ['nullable', 'string', 'max:80'],
            'model_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'exterior_color' => ['nullable', 'string', 'max:50'],
            'interior_color' => ['nullable', 'string', 'max:50'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'lifecycle_status' => ['required', 'string', 'max:50'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

