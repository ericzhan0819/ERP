<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AccountingEventConvertPreflightService
{
    public function __construct(
        private readonly AccountingJournalValidator $journalValidator,
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

        $accounts = $this->resolveRequiredAccounts($event, $mapping);
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

    /**
     * 技術註解：科目解析只信任後端 mapping metadata，不接受前端 account_id，防止 IDOR、跨租戶科目引用與錯誤科目類型導致錯誤認列。
     *
     * @param array<string, mixed> $mapping
     * @return array<string, array{account: AccountingAccount, label: string}>
     */
    private function resolveRequiredAccounts(AccountingEvent $event, array $mapping): array
    {
        $requiredKeys = $mapping['required_mapping_keys'] ?? null;
        $mappingKeys = $mapping['mapping_keys'] ?? null;

        if (! is_array($requiredKeys) || ! is_array($mappingKeys)) {
            throw ValidationException::withMessages(['mapping' => '會計事件映射尚未指定必要科目，無法產生傳票草稿。']);
        }

        $resolved = [];

        foreach ($requiredKeys as $key) {
            $metadata = is_string($key) ? ($mappingKeys[$key] ?? null) : null;

            if (! is_string($key) || ! is_array($metadata) || empty($metadata['runtime_account_id'])) {
                throw ValidationException::withMessages(['mapping' => '會計事件映射尚未指定必要科目，無法產生傳票草稿。']);
            }

            $account = AccountingAccount::query()->find((int) $metadata['runtime_account_id']);

            if (! $account || ! $this->isValidMappedAccount($event, $account, $metadata)) {
                throw ValidationException::withMessages(['mapping' => '會計事件映射科目無效，無法產生傳票草稿。']);
            }

            $resolved[$key] = [
                'account' => $account,
                'label' => (string) ($metadata['label'] ?? $key),
            ];
        }

        foreach (['accounts_receivable_account', 'sales_revenue_account'] as $requiredPreviewKey) {
            if (! array_key_exists($requiredPreviewKey, $resolved)) {
                throw ValidationException::withMessages(['mapping' => '會計事件映射尚未指定必要科目，無法產生傳票草稿。']);
            }
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function isValidMappedAccount(AccountingEvent $event, AccountingAccount $account, array $metadata): bool
    {
        $intendedTypes = $metadata['intended_account_types'] ?? [];

        return (int) $account->company_id === (int) $event->company_id
            && ($account->branch_id === null || (int) $account->branch_id === (int) $event->branch_id)
            && $account->is_active === true
            && is_array($intendedTypes)
            && in_array($account->type, $intendedTypes, true);
    }
}
