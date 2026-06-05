<?php

namespace App\Services;

use App\Models\AccountingAccount;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AccountingJournalValidator
{
    /**
     * 技術註解：借貸平衡與 account scope 驗證集中在 service，避免 controller/store/update 散落重複規則。
     *
     * @param array<int, array<string, mixed>> $lines
     */
    public function validateDraftLines(array $lines, int $companyId): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => '傳票至少需要兩列分錄。']);
        }

        $normalizedLines = collect($lines)->map(function (array $line, int $index): array {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages(["lines.$index" => '單列分錄不可同時輸入借方與貸方。']);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw ValidationException::withMessages(["lines.$index" => '單列分錄借方與貸方不可同時為 0。']);
            }

            return [
                'account_id' => (int) ($line['account_id'] ?? 0),
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $line['memo'] ?? null,
                'sort_order' => (int) ($line['sort_order'] ?? $index),
            ];
        });

        $totalDebit = round((float) $normalizedLines->sum('debit'), 2);
        $totalCredit = round((float) $normalizedLines->sum('credit'), 2);

        if ($totalDebit <= 0) {
            throw ValidationException::withMessages(['lines' => '借方總額必須大於 0。']);
        }

        if (bccomp((string) $totalDebit, (string) $totalCredit, 2) !== 0) {
            throw ValidationException::withMessages(['lines' => '借方總額與貸方總額必須相等。']);
        }

        $accountIds = $normalizedLines->pluck('account_id')->filter()->unique()->values();
        $activeAccountIds = AccountingAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $accountIds)
            ->pluck('id');

        if ($accountIds->count() !== $activeAccountIds->count()) {
            throw ValidationException::withMessages(['lines' => '分錄科目必須屬於同一公司且為啟用中的會計科目。']);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return Collection<int, array<string, mixed>>
     */
    public function normalizeLines(array $lines): Collection
    {
        return collect($lines)->values()->map(function (array $line, int $index): array {
            return [
                'account_id' => (int) ($line['account_id'] ?? 0),
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
                'memo' => filled($line['memo'] ?? null) ? (string) $line['memo'] : null,
                'sort_order' => (int) ($line['sort_order'] ?? $index),
            ];
        });
    }
}