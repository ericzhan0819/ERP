<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountingJournalEntryRequest extends FormRequest
{
    /**
     * 技術註解：授權由 controller 在 tenant-scoped 與 policy 層集中處理，request 僅負責輸入白名單。
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