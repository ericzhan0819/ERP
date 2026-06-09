<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountingEventAccountMappingRequest extends FormRequest
{
    /**
     * 技術註解：授權集中在 Controller/Policy，Request 僅負責欄位白名單，避免權限判斷分散造成漂移。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 技術註解：不接受 source_type、company_id、created_by、updated_by，防止前端偽造租戶、來源類型或 actor 欄位。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'event_type' => ['required', 'string', Rule::in(['vehicle_sale_completed'])],
            'mapping_key' => ['required', 'string', Rule::in(['accounts_receivable_account', 'sales_revenue_account'])],
            'account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
