<?php

namespace App\Services;

use App\Models\AccountingEvent;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AccountingEventJournalDraftPreflightService
{
    public function __construct(
        private readonly AccountingJournalValidator $journalValidator,
        private readonly AccountingEventAccountMappingResolver $mappingResolver,
    ) {}

    /**
     * 技術註解：Phase 4D-2A 只做轉傳票前置檢查與後端預覽，刻意不寫入傳票、分錄、事件狀態或稽核紀錄，避免尚未啟用的會計認列流程被前端觸發。
     *
     * @return array<string, mixed>
     */
    public function preview(AccountingEvent $event, User $user): array
    {
        $this->ensureTenantScope($event, $user);
        $this->ensurePermissions($user);
        $this->ensureEventState($event);

        $amount = round((float) $event->amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => '會計事件金額必須大於 0，無法產生傳票草稿。']);
        }

        $mapping = $this->mappingFor($event);
        $accounts = $this->mappingResolver->resolveRequiredAccounts($event, $mapping);
        $amountText = $this->formatAmount($amount);
        $summary = '車輛交易完成轉傳票：'.$event->source_number;
        $entryDate = optional($event->event_date)->toDateString();
        $lines = [
            [
                'mapping_key' => 'accounts_receivable_account',
                'account_id' => (int) $accounts['accounts_receivable_account']['account']->id,
                'account_code' => $accounts['accounts_receivable_account']['account']->code,
                'account_name' => $accounts['accounts_receivable_account']['account']->name,
                'account_type' => $accounts['accounts_receivable_account']['account']->type,
                'debit' => $amountText,
                'credit' => '0.00',
                'memo' => '應收帳款／收款清算',
                'sort_order' => 0,
            ],
            [
                'mapping_key' => 'sales_revenue_account',
                'account_id' => (int) $accounts['sales_revenue_account']['account']->id,
                'account_code' => $accounts['sales_revenue_account']['account']->code,
                'account_name' => $accounts['sales_revenue_account']['account']->name,
                'account_type' => $accounts['sales_revenue_account']['account']->type,
                'debit' => '0.00',
                'credit' => $amountText,
                'memo' => '車輛銷售收入',
                'sort_order' => 1,
            ],
        ];

        $this->journalValidator->validateDraftLines($lines, (int) $event->company_id);

        return [
            'header' => [
                'entry_date' => $entryDate,
                'summary' => $summary,
                'status' => 'draft',
                'source_type' => 'accounting_event',
                'source_id' => (int) $event->id,
            ],
            'event_id' => (int) $event->id,
            'event_type' => $event->event_type,
            'source_type' => $event->source_type,
            'source_id' => (int) $event->source_id,
            'source_number' => $event->source_number,
            'entry_date' => $entryDate,
            'summary' => $summary,
            'status' => 'draft',
            'source' => [
                'type' => 'accounting_event',
                'id' => (int) $event->id,
            ],
            'lines' => $lines,
            'totals' => [
                'debit' => $amountText,
                'credit' => $amountText,
                'difference' => '0.00',
            ],
            'warnings' => [],
        ];
    }

    private function ensureTenantScope(AccountingEvent $event, User $user): void
    {
        if ((int) ($user->company_id ?? 0) !== (int) $event->company_id) {
            abort(404);
        }

        if ($user->branch_id !== null && $event->branch_id !== null && (int) $user->branch_id !== (int) $event->branch_id) {
            abort(404);
        }
    }

    private function ensurePermissions(User $user): void
    {
        if (! $user->can('module.accounting.events.convert')) {
            abort(403);
        }

        if (! $user->can('module.accounting.journals.create')) {
            throw ValidationException::withMessages(['permission' => '沒有建立會計傳票的權限。']);
        }
    }

    private function ensureEventState(AccountingEvent $event): void
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
    private function mappingFor(AccountingEvent $event): array
    {
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

        return $mapping;
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
