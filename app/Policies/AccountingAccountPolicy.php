<?php

namespace App\Policies;

use App\Models\AccountingAccount;
use App\Models\User;

class AccountingAccountPolicy
{
    /**
     * 技術註解：viewAny 只檢查正式 accounts.view 權限，tenant 範圍由 controller query 統一處理，避免清單與明細條件漂移。
     */
    public function viewAny(User $user): bool
    {
        return $user->can('module.accounting.accounts.view');
    }

    /**
     * 技術註解：明細檢查需同時驗證 accounts.view 與 tenant 邊界，防止跨公司科目被直接 URL 存取。
     */
    public function view(User $user, AccountingAccount $account): bool
    {
        return $user->can('module.accounting.accounts.view')
            && $this->isSameTenant($user, $account);
    }

    /**
     * 技術註解：建立權限獨立於 module.view，可避免只有查看權限者寫入財務主檔。
     */
    public function create(User $user): bool
    {
        return $user->can('module.accounting.accounts.create');
    }

    /**
     * 技術註解：更新需同時驗證 accounts.update 與 tenant 邊界，避免跨租戶或跨分店的科目被修改。
     */
    public function update(User $user, AccountingAccount $account): bool
    {
        return $user->can('module.accounting.accounts.update')
            && $this->isSameTenant($user, $account);
    }

    private function isSameTenant(User $user, AccountingAccount $account): bool
    {
        $userCompanyId = (int) ($user->company_id ?? 0);

        if ($userCompanyId <= 0 || $userCompanyId !== (int) $account->company_id) {
            return false;
        }

        $userBranchId = $user->branch_id;

        return $userBranchId === null
            || $account->branch_id === null
            || (int) $userBranchId === (int) $account->branch_id;
    }
}