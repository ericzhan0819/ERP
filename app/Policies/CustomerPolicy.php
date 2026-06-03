<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * 技術註解：客戶主檔入口必須具備 module.customers.view，前端導覽可見性不可作為安全依據。
     */
    public function viewAny(User $user): bool
    {
        return $user->can('module.customers.view');
    }

    /**
     * 技術註解：讀取需同時符合權限與 company/branch 邊界，防止跨租戶 IDOR。
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->can('module.customers.view') && $this->withinTenant($user, $customer);
    }

    public function create(User $user): bool
    {
        return $user->can('module.customers.create');
    }

    /**
     * 技術註解：一般更新不得涵蓋敏感個資，個資另由 updateSensitive 權限定義。
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->can('module.customers.update') && $this->withinTenant($user, $customer);
    }

    public function viewSensitive(User $user, Customer $customer): bool
    {
        return $user->can('module.customers.sensitive.view') && $this->withinTenant($user, $customer);
    }

    public function updateSensitive(User $user, Customer $customer): bool
    {
        return $user->can('module.customers.sensitive.update') && $this->withinTenant($user, $customer);
    }

    private function withinTenant(User $user, Customer $customer): bool
    {
        $userCompanyId = (int) ($user->company_id ?? 0);
        if ($userCompanyId <= 0 || $userCompanyId !== (int) $customer->company_id) {
            return false;
        }

        $userBranchId = $user->branch_id ?? null;

        return $userBranchId === null || (int) $userBranchId === (int) $customer->branch_id;
    }
}

