<?php

namespace App\Policies;

use App\Models\AccountingJournalEntry;
use App\Models\User;

class AccountingJournalEntryPolicy
{
    /**
     * 技術註解：清單只檢查 journals.view；tenant 範圍固定由 controller scoped query 控管。
     */
    public function viewAny(User $user): bool
    {
        return $user->can('module.accounting.journals.view');
    }

    /**
     * 技術註解：明細需同時驗證 journals.view 與 tenant 邊界，防止跨公司傳票被直接 URL 存取。
     */
    public function view(User $user, AccountingJournalEntry $journalEntry): bool
    {
        return $user->can('module.accounting.journals.view')
            && $this->isSameTenant($user, $journalEntry);
    }

    /**
     * 技術註解：建立權限獨立於清單權限，避免只有查看者可寫入財務草稿。
     */
    public function create(User $user): bool
    {
        return $user->can('module.accounting.journals.create');
    }

    /**
     * 技術註解：更新除 journals.update 外還要限制在 draft，避免已完成流程的傳票被覆寫。
     */
    public function update(User $user, AccountingJournalEntry $journalEntry): bool
    {
        return $user->can('module.accounting.journals.update')
            && $this->isSameTenant($user, $journalEntry)
            && $journalEntry->status === 'draft';
    }

    private function isSameTenant(User $user, AccountingJournalEntry $journalEntry): bool
    {
        $userCompanyId = (int) ($user->company_id ?? 0);

        if ($userCompanyId <= 0 || $userCompanyId !== (int) $journalEntry->company_id) {
            return false;
        }

        return $user->branch_id === null
            || $journalEntry->branch_id === null
            || (int) $user->branch_id === (int) $journalEntry->branch_id;
    }
}