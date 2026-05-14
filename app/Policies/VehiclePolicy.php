<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * 技術註解：所有存取都必須先通過 module.vehicle.view 權限，避免前端可見性被誤當成授權。
     */
    public function viewAny(User $user): bool
    {
        return $user->can('module.vehicle.view');
    }

    /**
     * 技術註解：以 company/branch 邊界防止跨租戶 IDOR；即使使用者有 view 權限也不得跨邊界讀取。
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        if (! $user->can('module.vehicle.view')) {
            return false;
        }

        $userCompanyId = (int) ($user->company_id ?? 0);
        if ($userCompanyId <= 0 || $userCompanyId !== (int) $vehicle->company_id) {
            return false;
        }

        $userBranchId = $user->branch_id ?? null;

        return $userBranchId === null || (int) $userBranchId === (int) $vehicle->branch_id;
    }

    public function create(User $user): bool
    {
        return $user->can('module.vehicle.create');
    }

    /**
     * 技術註解：更新同樣強制 company/branch 邊界，避免以直接 URL 修改他公司或他分店資料。
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        if (! $user->can('module.vehicle.update')) {
            return false;
        }

        $userCompanyId = (int) ($user->company_id ?? 0);
        if ($userCompanyId <= 0 || $userCompanyId !== (int) $vehicle->company_id) {
            return false;
        }

        $userBranchId = $user->branch_id ?? null;

        return $userBranchId === null || (int) $userBranchId === (int) $vehicle->branch_id;
    }
}

