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

    /**
     * 技術註解：覆核是財務狀態轉換，只接受獨立 review 權限與 pending 狀態，避免 view 或相容 accounting 權限被擴張成 mutation 能力。
     */
    public function review(User $user, AccountingEvent $event): bool
    {
        return $user->can('module.accounting.events.review')
            && $this->isSameTenant($user, $event)
            && $event->status === 'pending'
            && $event->voided_at === null
            && $event->converted_journal_entry_id === null;
    }

    /**
     * 技術註解：作廢僅限尚未轉傳票的 pending/reviewed 事件，避免未設計的 journal cancellation、reversal 或營收/成本認列被繞過。
     */
    public function void(User $user, AccountingEvent $event): bool
    {
        return $user->can('module.accounting.events.void')
            && $this->isSameTenant($user, $event)
            && in_array($event->status, ['pending', 'reviewed'], true)
            && $event->voided_at === null
            && $event->converted_journal_entry_id === null;
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
