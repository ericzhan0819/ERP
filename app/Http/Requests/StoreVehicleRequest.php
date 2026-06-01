<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleRequest extends FormRequest
{
    /**
     * 技術註解：建立車輛前先走 Policy create，避免未授權使用者送出寫入請求造成權限繞過風險。
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->can('create', Vehicle::class)) {
            return false;
        }

        // 技術註解：若未授權者嘗試夾帶 pricing 欄位，需優先回 403 阻斷未授權價格寫入嘗試。
        if ($this->hasAny(['asking_price', 'floor_price']) && ! $user->can('module.vehicles.pricing.update')) {
            return false;
        }

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
            'asking_price' => ['nullable', 'numeric', 'min:0'],
            'floor_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * 技術註解：以最小複雜度補上跨欄位驗證，防止 floor_price 高於 asking_price 造成價格資料不一致。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $askingPrice = $this->input('asking_price');
            $floorPrice = $this->input('floor_price');

            if ($askingPrice !== null && $floorPrice !== null && (float) $floorPrice > (float) $askingPrice) {
                $validator->errors()->add('floor_price', '底價不可高於開價。');
            }
        });
    }
}
