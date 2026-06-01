<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCost;

class VehicleCostPolicy
{
    /**
     * 技術註解：成本檢視需同時滿足 costs.view 與 tenant 邊界，避免跨公司/分店財務資訊外洩。
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        if (! $user->can('module.vehicles.costs.view')) {
            return false;
        }

        return $this->isSameTenantWithVehicle($user, $vehicle);
    }

    /**
     * 技術註解：建立成本需具備 create 權限且車輛在同一 tenant，避免越權寫入他租戶資料。
     */
    public function create(User $user, Vehicle $vehicle): bool
    {
        if (! $user->can('module.vehicles.costs.create')) {
            return false;
        }

        return $this->isSameTenantWithVehicle($user, $vehicle);
    }

    /**
     * 技術註解：更新成本除 update 權限外，需同時驗證 vehicle/cost tenant 與 route vehicle 一致，防止 IDOR。
     */
    public function update(User $user, VehicleCost $vehicleCost, Vehicle $vehicle): bool
    {
        if (! $user->can('module.vehicles.costs.update')) {
            return false;
        }

        if (! $this->isSameTenantWithVehicle($user, $vehicle)) {
            return false;
        }

        if ((int) $vehicleCost->vehicle_id !== (int) $vehicle->id) {
            return false;
        }

        return (int) $vehicleCost->company_id === (int) $vehicle->company_id
            && (int) $vehicleCost->branch_id === (int) $vehicle->branch_id;
    }

    /**
     * 技術註解：集中 tenant 判斷可避免多處重複邏輯造成授權條件漂移。
     */
    private function isSameTenantWithVehicle(User $user, Vehicle $vehicle): bool
    {
        $userCompanyId = (int) ($user->company_id ?? 0);
        if ($userCompanyId <= 0 || $userCompanyId !== (int) $vehicle->company_id) {
            return false;
        }

        $userBranchId = $user->branch_id;

        return $userBranchId === null || (int) $userBranchId === (int) $vehicle->branch_id;
    }
}

