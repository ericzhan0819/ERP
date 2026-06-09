<?php

namespace App\Services;

use App\Models\AccountingEvent;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AccountingEventConvertPreflightService
{
    public function __construct(
        private readonly AccountingJournalValidator $journalValidator,
        private readonly AccountingEventAccountMappingResolver $mappingResolver,
    ) {}

    /**
     * 技術註解：Phase 4D-2A 僅產生後端驗證過的預覽資料，不建立傳票、不寫分錄、不改事件狀態，避免前置檢查被誤用成正式認列流程。
     *
     * @return array<string, mixed>
     */
    public function preview(AccountingEvent $event, User $user): array
    {
        $this->ensureUserCanPreview($event, $user);
        $this->ensureEventCanPreview($event);

        $amount = round((float) $event->amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => '會計事件金額必須大於 0。']);
        }

        $mapping = config('accounting_event_mappings.event_types.'.$event->event_type);

        if (! is_array($mapping)) {
            throw ValidationException::withMessages(['mapping' => '找不到會計事件映射設定，無法產生傳票草稿。']);
        }

        if (($mapping['source_type'] ?? null) !== $event->source_type) {
            throw ValidationException::withMessages(['mapping' => '會計事件映射與來源類型不一致，無法產生傳票草稿。']);
        }

        if (($mapping['enabled'] ?? false) !== true) {
            throw ValidationException::withMessages(['mapping' => '會計事件映射尚未啟用，無法產生傳票草稿。']);
        }

        $accounts = $this->mappingResolver->resolveRequiredAccounts($event, $mapping);
        $memo = '車輛交易完成轉傳票：'.$event->source_number;
        $lines = [
            [
                'account_id' => $accounts['accounts_receivable_account']['account']->id,
                'debit' => $amount,
                'credit' => 0.0,
                'memo' => $memo,
                'sort_order' => 1,
            ],
            [
                'account_id' => $accounts['sales_revenue_account']['account']->id,
                'debit' => 0.0,
                'credit' => $amount,
                'memo' => $memo,
                'sort_order' => 2,
            ],
        ];

        $this->journalValidator->validateDraftLines($lines, (int) $event->company_id);

        return [
            'header' => [
                'company_id' => (int) $event->company_id,
                'branch_id' => $event->branch_id === null ? null : (int) $event->branch_id,
                'entry_date' => $event->event_date->toDateString(),
                'status' => 'draft',
                'source_type' => 'accounting_event',
                'source_id' => (int) $event->id,
                'summary' => $memo,
            ],
            'lines' => $lines,
            'mapping_key_labels' => collect($accounts)->mapWithKeys(fn (array $resolved, string $key): array => [
                $key => $resolved['label'],
            ])->all(),
            'accounts' => collect($accounts)->mapWithKeys(fn (array $resolved, string $key): array => [
                $key => [
                    'id' => $resolved['account']->id,
                    'name' => $resolved['account']->name,
                    'type' => $resolved['account']->type,
                ],
            ])->all(),
            'total_debit' => $amount,
            'total_credit' => $amount,
            'amount' => $amount,
            'event_id' => (int) $event->id,
            'source_number' => $event->source_number,
            'event_type' => $event->event_type,
        ];
    }

    private function ensureUserCanPreview(AccountingEvent $event, User $user): void
    {
        if (! $user->can('module.accounting.events.convert')) {
            abort(403);
        }

        if (! $user->can('module.accounting.journals.create')) {
            throw ValidationException::withMessages(['permission' => '沒有建立會計傳票的權限。']);
        }

        if ((int) ($user->company_id ?? 0) !== (int) $event->company_id) {
            abort(404);
        }

        if ($user->branch_id !== null && $event->branch_id !== null && (int) $user->branch_id !== (int) $event->branch_id) {
            abort(404);
        }
    }

    private function ensureEventCanPreview(AccountingEvent $event): void
    {
        if ($event->converted_journal_entry_id !== null) {
            throw ValidationException::withMessages(['event' => '此會計事件已產生傳票草稿。']);
        }

        if ($event->status !== 'reviewed' || $event->voided_at !== null) {
            throw ValidationException::withMessages(['event' => '只有已覆核的會計事件可以產生傳票草稿。']);
        }
    }

}
