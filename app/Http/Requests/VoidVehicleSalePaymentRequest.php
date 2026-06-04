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