<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountingAccountRequest extends FormRequest
{
    /**
     * 技術註解：授權由 Controller 在已 scoped 的科目實體上判斷，避免未隔離 route 參數先行授權造成 IDOR 風險。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 技術註解：更新只驗證可編輯業務欄位，不允許 tenant/actor 欄位進入 payload，以避免 mass assignment 與權限提升。
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