<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewAccountingEventRequest extends FormRequest
{
    /**
     * @var array<int, string>
     */
    private const FORBIDDEN_FIELDS = [
        'company_id',
        'branch_id',
        'source_type',
        'source_id',
        'source_number',
        'event_type',
        'event_date',
        'status',
        'currency',
        'amount',
        'payload',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'converted_journal_entry_id',
        'voided_by',
        'voided_at',
        'void_reason',
        'journal_entry_id',
        'journal_entry_number',
        'accounting_journal_entry_id',
        'revenue_amount',
        'cogs_amount',
        'profit',
        'gross_profit',
        'gross_margin',
        'gross_margin_rate',
        'purchase_cost',
        'customer_phone',
        'id_number',
        'birthday',
        'address',
    ];

    public function authorize(): bool
    {
        // 技術註解：拒絕系統欄位可防止 mass assignment、tenant injection、認列金額與傳票關聯遭前端 payload 越權竄改。
        return collect(self::FORBIDDEN_FIELDS)
            ->every(fn (string $field): bool => ! $this->has($field));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
