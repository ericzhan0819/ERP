<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingEvent extends Model
{
    /**
     * 技術註解：會計事件目前僅作後端 foundation snapshot，不開放任何前端 runtime 流程自動寫入會計真實依據。
     *
     * @var array<int, string>
     */
    protected $fillable = [
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
        'review_note',
        'created_by',
        'reviewed_by',
        'converted_journal_entry_id',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'source_id' => 'integer',
            'event_date' => 'date',
            'amount' => 'decimal:2',
            'payload' => 'array',
            'created_by' => 'integer',
            'reviewed_by' => 'integer',
            'converted_journal_entry_id' => 'integer',
            'voided_by' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function convertedJournalEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'converted_journal_entry_id');
    }
}
