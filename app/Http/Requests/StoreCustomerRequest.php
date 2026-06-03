<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    /**
     * 技術註解：建立授權包含一般 create 與敏感欄位 updateSensitive，避免未授權者透過夾帶欄位寫入個資。
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->can('create', Customer::class)) {
            return false;
        }

        if ($this->hasAny($this->systemManagedFields())) {
            // 技術註解：系統欄位一律拒絕前端覆寫，防止 mass assignment、租戶污染與審計歸屬偽造。
            return false;
        }

        if ($this->hasAny($this->sensitiveFields()) && ! $user->can('module.customers.sensitive.update')) {
            // 技術註解：敏感個資不得被靜默吞掉，直接回 403 讓越權嘗試可被測試與稽核辨識。
            return false;
        }

        return true;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'line_id' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::in(array_keys(config('customers.statuses')))],
            'source' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'birthday' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sensitiveFields(): array
    {
        return ['id_number', 'birthday', 'address'];
    }

    /**
     * @return array<int, string>
     */
    private function systemManagedFields(): array
    {
        return ['company_id', 'branch_id', 'customer_number', 'created_by', 'updated_by'];
    }
}

