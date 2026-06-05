<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountingAccountRequest extends FormRequest
{
    /**
     * 技術註解：授權由 Controller 在 tenant-scoped 查詢與 Policy 檢查後執行，這裡僅保留 true 以集中後端安全邏輯。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 技術註解：不允許 company_id、branch_id、created_by、updated_by 由前端直接進入驗證，避免越權覆寫 tenant 與 actor 欄位。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(array_keys(config('accounting.account_types', [])))],
            'opening_balance' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}