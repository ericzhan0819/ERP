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
     * 技術註解：company_id、created_by、updated_by 仍由 Controller 寫入；source_type 只允許 metadata 值，避免前端偽造 runtime 來源。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'event_type' => ['required', 'string', Rule::in(['vehicle_sale_completed'])],
            'source_type' => ['nullable', 'string', Rule::in([$this->sourceType()])],
            'mapping_key' => ['required', 'string', Rule::in($this->firstScopeMappingKeys())],
            'account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function sourceType(): string
    {
        return (string) config('accounting_event_mappings.event_types.vehicle_sale_completed.source_type', 'vehicle_sale_completion');
    }

    /**
     * @return array<int, string>
     */
    private function firstScopeMappingKeys(): array
    {
        $requiredKeys = config('accounting_event_mappings.event_types.vehicle_sale_completed.required_mapping_keys', []);

        return array_values(array_intersect(
            is_array($requiredKeys) ? $requiredKeys : [],
            ['accounts_receivable_account', 'sales_revenue_account']
        ));
    }
}
