<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AccountingEventJournalDraftPreflightService
{
    public function __construct(
        private readonly AccountingJournalValidator $journalValidator,
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
        $accounts = $this->resolveRequiredAccounts($event, $user, $mapping);
        $amountText = $this->formatAmount($amount);
        $summary = '車輛交易完成轉傳票：'.$event->source_number;
        $lines = [
            [
                'mapping_key' => 'accounts_receivable_account',
                'account_id' => (int) $accounts['accounts_receivable_account']->id,
                'account_code' => $accounts['accounts_receivable_account']->code,
                'account_name' => $accounts['accounts_receivable_account']->name,
                'account_type' => $accounts['accounts_receivable_account']->type,
                'debit' => $amountText,
                'credit' => '0.00',
                'memo' => '應收帳款／收款清算',
                'sort_order' => 0,
            ],
            [
                'mapping_key' => 'sales_revenue_account',
                'account_id' => (int) $accounts['sales_revenue_account']->id,
                'account_code' => $accounts['sales_revenue_account']->code,
                'account_name' => $accounts['sales_revenue_account']->name,
                'account_type' => $accounts['sales_revenue_account']->type,
                'debit' => '0.00',
                'credit' => $amountText,
                'memo' => '車輛銷售收入',
                'sort_order' => 1,
            ],
        ];

        $this->journalValidator->validateDraftLines($lines, (int) $event->company_id);

        return [
            'event_id' => (int) $event->id,
            'event_type' => $event->event_type,
            'source_type' => $event->source_type,
            'source_id' => (int) $event->source_id,
            'source_number' => $event->source_number,
            'entry_date' => optional($event->event_date)->toDateString(),
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

    /**
     * 技術註解：科目解析只信任後端 mapping metadata 的 runtime_account_id，並同時檢查 company/branch/type/active，避免 IDOR 與跨分店科目被用於正式草稿。
     *
     * @param array<string, mixed> $mapping
     * @return array<string, AccountingAccount>
     */
    private function resolveRequiredAccounts(AccountingEvent $event, User $user, array $mapping): array
    {
        $requiredKeys = $mapping['required_mapping_keys'] ?? [];
        $mappingKeys = $mapping['mapping_keys'] ?? [];
        $accounts = [];

        foreach ($requiredKeys as $key) {
            if (! is_string($key) || ! isset($mappingKeys[$key]) || ! is_array($mappingKeys[$key])) {
                throw ValidationException::withMessages(['mapping' => '會計事件映射尚未指定必要科目，無法產生傳票草稿。']);
            }

            $accountId = $mappingKeys[$key]['runtime_account_id'] ?? null;

            if (! is_numeric($accountId) || (int) $accountId <= 0) {
                throw ValidationException::withMessages(['mapping' => '會計事件映射尚未指定必要科目，無法產生傳票草稿。']);
            }

            $account = AccountingAccount::query()->find((int) $accountId);

            if (! $account instanceof AccountingAccount || ! $this->accountScopeIsValid($account, $event, $user)) {
                throw ValidationException::withMessages(['mapping' => '會計事件映射科目無效，無法產生傳票草稿。']);
            }

            $intendedTypes = $mappingKeys[$key]['intended_account_types'] ?? [];

            if (! in_array($account->type, is_array($intendedTypes) ? $intendedTypes : [], true)) {
                throw ValidationException::withMessages(['mapping' => '會計事件映射科目類型不符，無法產生傳票草稿。']);
            }

            $accounts[$key] = $account;
        }

        if (! isset($accounts['accounts_receivable_account'], $accounts['sales_revenue_account'])) {
            throw ValidationException::withMessages(['mapping' => '會計事件映射尚未指定必要科目，無法產生傳票草稿。']);
        }

        return $accounts;
    }

    private function accountScopeIsValid(AccountingAccount $account, AccountingEvent $event, User $user): bool
    {
        if ((int) $account->company_id !== (int) $event->company_id || ! (bool) $account->is_active) {
            return false;
        }

        if ($account->branch_id === null) {
            return true;
        }

        if ($user->branch_id !== null && (int) $account->branch_id !== (int) $user->branch_id) {
            return false;
        }

        if ($event->branch_id !== null && (int) $account->branch_id !== (int) $event->branch_id) {
            return false;
        }

        return true;
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
