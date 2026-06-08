<?php

namespace App\Policies;

use App\Models\AccountingEvent;
use App\Models\User;

class AccountingEventPolicy
{
    /**
     * 技術註解：只讀工作台的清單授權僅檢查獨立 events.view，tenant 範圍固定由 Controller query 處理，避免與 module.accounting.view 相容權限混用。
     */
    public function viewAny(User $user): bool
    {
        return $user->can('module.accounting.events.view');
    }

    /**
     * 技術註解：明細授權同時檢查獨立檢視權限與租戶邊界，防止直接 URL 探測跨公司或跨分店會計候選事件。
     */
    public function view(User $user, AccountingEvent $event): bool
    {
        return $user->can('module.accounting.events.view')
            && $this->isSameTenant($user, $event);
    }

    private function isSameTenant(User $user, AccountingEvent $event): bool
    {
        $userCompanyId = (int) ($user->company_id ?? 0);

        if ($userCompanyId <= 0 || $userCompanyId !== (int) $event->company_id) {
            return false;
        }

        if ($user->branch_id === null) {
            return true;
        }

        return $event->branch_id === null
            || (int) $user->branch_id === (int) $event->branch_id;
    }
}
