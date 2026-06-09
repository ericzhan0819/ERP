<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingEventAccountMapping;
use Illuminate\Validation\ValidationException;

class AccountingEventAccountMappingResolver
{
    private const MISSING_MAPPING_MESSAGE = '會計事件映射尚未指定必要科目，無法產生傳票草稿。';

    private const INVALID_ACCOUNT_MESSAGE = '會計事件映射科目無效，無法產生傳票草稿。';

    /**
     * 技術註解：只解析後端 metadata 宣告的必要 mapping key，實際 account_id 來自 DB mapping，避免 committed config 帶入正式科目 ID。
     *
     * @param array<string, mixed> $mapping
     * @return array<string, array{account: AccountingAccount, label: string}>
     */
    public function resolveRequiredAccounts(AccountingEvent $event, array $mapping): array
    {
        $requiredKeys = $mapping['required_mapping_keys'] ?? null;
        $mappingKeys = $mapping['mapping_keys'] ?? null;

        if (! is_array($requiredKeys) || ! is_array($mappingKeys)) {
            throw ValidationException::withMessages(['mapping' => self::MISSING_MAPPING_MESSAGE]);
        }

        $resolved = [];

        foreach ($requiredKeys as $key) {
            $metadata = is_string($key) ? ($mappingKeys[$key] ?? null) : null;

            if (! is_string($key) || ! is_array($metadata) || ! in_array($key, ['accounts_receivable_account', 'sales_revenue_account'], true)) {
                throw ValidationException::withMessages(['mapping' => self::MISSING_MAPPING_MESSAGE]);
            }

            $accountMapping = $this->findMapping($event, $key);

            if (! $accountMapping || ! $accountMapping->account) {
                throw ValidationException::withMessages(['mapping' => self::MISSING_MAPPING_MESSAGE]);
            }

            if (! $this->isValidMappedAccount($event, $accountMapping->account, $metadata)) {
                throw ValidationException::withMessages(['mapping' => self::INVALID_ACCOUNT_MESSAGE]);
            }

            $resolved[$key] = [
                'account' => $accountMapping->account,
                'label' => (string) ($metadata['label'] ?? $key),
            ];
        }

        foreach (['accounts_receivable_account', 'sales_revenue_account'] as $requiredPreviewKey) {
            if (! array_key_exists($requiredPreviewKey, $resolved)) {
                throw ValidationException::withMessages(['mapping' => self::MISSING_MAPPING_MESSAGE]);
            }
        }

        return $resolved;
    }

    private function findMapping(AccountingEvent $event, string $mappingKey): ?AccountingEventAccountMapping
    {
        $baseQuery = AccountingEventAccountMapping::query()
            ->with('account')
            ->where('company_id', (int) $event->company_id)
            ->where('event_type', $event->event_type)
            ->where('source_type', $event->source_type)
            ->where('mapping_key', $mappingKey)
            ->where('is_active', true);

        if ($event->branch_id !== null) {
            $branchMapping = (clone $baseQuery)
                ->where('branch_id', (int) $event->branch_id)
                ->first();

            if ($branchMapping) {
                return $branchMapping;
            }
        }

        return $baseQuery->whereNull('branch_id')->first();
    }

    /**
     * 技術註解：科目必須符合租戶、分店、啟用狀態與 metadata 類型，防止 IDOR、跨租戶科目引用與錯誤科目類型造成錯誤傳票。
     *
     * @param array<string, mixed> $metadata
     */
    private function isValidMappedAccount(AccountingEvent $event, AccountingAccount $account, array $metadata): bool
    {
        $intendedTypes = $metadata['intended_account_types'] ?? [];

        return (int) $account->company_id === (int) $event->company_id
            && ($account->branch_id === null || ($event->branch_id !== null && (int) $account->branch_id === (int) $event->branch_id))
            && $account->is_active === true
            && is_array($intendedTypes)
            && in_array($account->type, $intendedTypes, true);
    }
}
