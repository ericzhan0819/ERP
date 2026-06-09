<?php

namespace App\Policies;

use App\Models\AccountingEventAccountMapping;
use App\Models\User;

class AccountingEventAccountMappingPolicy
{
    /**
     * 技術註解：列表入口只看正式 mapping view 權限，tenant 範圍由 controller query 統一套用避免 IDOR。
     */
    public function viewAny(User $user): bool
    {
        return $user->can('module.accounting.event-mappings.view');
    }

    /**
     * 技術註解：建立權限獨立於 view，避免只讀會計人員可修改未來傳票科目映射。
     */
    public function create(User $user): bool
    {
        return $user->can('module.accounting.event-mappings.create');
    }

    /**
     * 技術註解：單筆檢視沿用正式 mapping view 權限並檢查 tenant/branch，避免以寬鬆會計權限讀取跨租戶映射。
     */
    public function view(User $user, AccountingEventAccountMapping $mapping): bool
    {
        if (! $user->can('module.accounting.event-mappings.view')) {
            return false;
        }

        if ((int) ($user->company_id ?? 0) !== (int) $mapping->company_id) {
            return false;
        }

        return $user->branch_id === null
            || $mapping->branch_id === null
            || (int) $user->branch_id === (int) $mapping->branch_id;
    }

    /**
     * 技術註解：更新需同時檢查權限與 tenant/branch 邊界，防止跨公司或跨分店覆寫會計映射造成錯誤認列風險。
     */
    public function update(User $user, AccountingEventAccountMapping $mapping): bool
    {
        if (! $user->can('module.accounting.event-mappings.update')) {
            return false;
        }

        if ((int) ($user->company_id ?? 0) !== (int) $mapping->company_id) {
            return false;
        }

        return $user->branch_id === null
            || $mapping->branch_id === null
            || (int) $user->branch_id === (int) $mapping->branch_id;
    }
}
