<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountingJournalEntryRequest extends FormRequest
{
    /**
     * 技術註解：更新授權與 draft 狀態限制由 controller/policy 控制，request 僅驗證輸入格式。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'summary' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
            'lines.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}