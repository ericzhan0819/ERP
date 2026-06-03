<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * 技術註解：實體 update 授權由 Controller 在 scoped 查詢後執行；此處先攔截系統欄位與未授權個資寫入。
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($this->hasAny($this->systemManagedFields())) {
            // 技術註解：拒絕前端覆寫租戶、流水號與審計欄位，降低 IDOR、序號污染與稽核偽造風險。
            return false;
        }

        if ($this->hasAny($this->sensitiveFields()) && ! $user->can('module.customers.sensitive.update')) {
            // 技術註解：敏感個資更新權限獨立於一般 customers.update，避免一般編輯權限擴張為個資權限。
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

