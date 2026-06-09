<?php

namespace App\Services;

use App\Models\AccountingEvent;
use App\Models\AccountingJournalEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingEventConvertService
{
    public function __construct(
        private readonly AccountingEventConvertPreflightService $preflightService,
        private readonly AccountingJournalNumberService $journalNumberService,
        private readonly AccountingJournalValidator $journalValidator,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function convert(AccountingEvent $event, User $user, ?Request $request = null): AccountingJournalEntry
    {
        return DB::transaction(function () use ($event, $user, $request): AccountingJournalEntry {
            $lockedEvent = AccountingEvent::query()
                ->with('reviewer:id,name')
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureEventCanConvert($lockedEvent);

            $preview = $this->preflightService->preview($lockedEvent, $user);
            $lines = $preview['lines'] ?? [];

            $this->journalValidator->validateDraftLines($lines, (int) $lockedEvent->company_id);
            $normalizedLines = $this->journalValidator->normalizeLines($lines);
            $header = is_array($preview['header'] ?? null) ? $preview['header'] : [];
            $entryDate = $header['entry_date'] ?? $preview['entry_date'] ?? null;
            $summary = $header['summary'] ?? $preview['summary'] ?? null;

            if (! is_string($entryDate) || $entryDate === '' || ! is_string($summary) || $summary === '') {
                throw ValidationException::withMessages(['header' => '傳票草稿表頭資料不完整，無法產生傳票草稿。']);
            }

            $journalNumber = $this->journalNumberService->generate((int) $lockedEvent->company_id, new \DateTimeImmutable($entryDate));

            // 技術註解：傳票 header 只使用後端 preflight 與事件 allowlist，避免前端注入 journal number、tenant 或分錄資料造成權限提升。
            $journal = AccountingJournalEntry::create([
                'company_id' => (int) $lockedEvent->company_id,
                'branch_id' => $lockedEvent->branch_id === null ? null : (int) $lockedEvent->branch_id,
                'journal_number' => $journalNumber,
                'entry_date' => $entryDate,
                'summary' => $summary,
                'status' => 'draft',
                'source_type' => 'accounting_event',
                'source_id' => (int) $lockedEvent->id,
                'created_by' => (int) $user->id,
                'updated_by' => (int) $user->id,
            ]);

            $journal->lines()->createMany($normalizedLines->all());

            $oldValues = $this->auditSnapshot($lockedEvent, 'old_status', null, $journalNumber);

            // 技術註解：只標記事件已轉草稿並連回傳票，不覆寫覆核欄位、payload 或金額，避免認列依據被轉換流程改寫。
            $lockedEvent->forceFill([
                'status' => 'converted',
                'converted_journal_entry_id' => $journal->id,
            ])->save();

            $this->auditLogService->log(
                $user,
                'accounting_event.converted',
                'Accounting event converted',
                null,
                ['module' => 'accounting_events'],
                $lockedEvent,
                $oldValues,
                $this->auditSnapshot($lockedEvent, 'new_status', $journal->id, $journalNumber),
                $request,
                'accounting_event.converted',
            );

            return $journal->load('lines');
        });
    }

    private function ensureEventCanConvert(AccountingEvent $event): void
    {
        if ($event->converted_journal_entry_id !== null) {
            throw ValidationException::withMessages(['event' => '此會計事件已產生傳票草稿。']);
        }

        if ($event->voided_at !== null) {
            throw ValidationException::withMessages(['event' => '已作廢的會計事件不可產生傳票草稿。']);
        }

        if ($event->status !== 'reviewed') {
            throw ValidationException::withMessages(['event' => '只有已覆核的會計事件可以產生傳票草稿。']);
        }
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(AccountingEvent $event, string $statusKey, ?int $journalId, string $journalNumber): array
    {
        return [
            'id' => (int) $event->id,
            'source_type' => $event->source_type,
            'source_id' => (int) $event->source_id,
            'source_number' => $event->source_number,
            'event_type' => $event->event_type,
            $statusKey => $event->status,
            'converted_journal_entry_id' => $journalId,
            'journal_number' => $journalNumber,
            'currency' => $event->currency,
            'reviewed_by_name' => $event->reviewer?->name,
            'reviewed_at' => $event->reviewed_at?->toDateTimeString(),
        ];
    }
}
