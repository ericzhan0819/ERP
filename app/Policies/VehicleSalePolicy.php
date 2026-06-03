<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSale;

class VehicleSalePolicy
{
    /**
     * 技術註解：銷售列表/明細需 sales.view，避免客戶電話、成交價與佣金資訊被一般車輛檢視權限外洩。
     */
    public function viewAny(User $user, Vehicle $vehicle): bool
    {
        if (! $user->can('module.vehicles.sales.view')) {
            return false;
        }

        return $this->isSameTenantWithVehicle($user, $vehicle);
    }

    /**
     * 技術註解：單筆銷售檢視同時驗證 sale 與 route vehicle 一致，防止 IDOR 讀取他車銷售紀錄。
     */
    public function view(User $user, VehicleSale $vehicleSale, Vehicle $vehicle): bool
    {
        if (! $user->can('module.vehicles.sales.view')) {
            return false;
        }

        return $this->isSameTenantWithSale($user, $vehicleSale, $vehicle);
    }

    /**
     * 技術註解：建立銷售需具備 sales.create 且車輛位於同一 tenant，避免越權注入他租戶銷售狀態。
     */
    public function create(User $user, Vehicle $vehicle): bool
    {
        if (! $user->can('module.vehicles.sales.create')) {
            return false;
        }

        return $this->isSameTenantWithVehicle($user, $vehicle);
    }

    /**
     * 技術註解：更新銷售需同時驗證 user、vehicle、sale 三方 tenant 與關聯一致，避免 IDOR 與跨車狀態污染。
     */
    public function update(User $user, VehicleSale $vehicleSale, Vehicle $vehicle): bool
    {
        if (! $user->can('module.vehicles.sales.update')) {
            return false;
        }

        return $this->isSameTenantWithSale($user, $vehicleSale, $vehicle);
    }

    /**
     * 技術註解：集中 vehicle tenant 判斷可避免多處重複條件漂移導致權限邊界不一致。
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

    /**
     * 技術註解：銷售紀錄必須與 route vehicle 綁定且 tenant 欄位一致，阻斷跨車與跨分店 IDOR。
     */
    private function isSameTenantWithSale(User $user, VehicleSale $vehicleSale, Vehicle $vehicle): bool
    {
        if (! $this->isSameTenantWithVehicle($user, $vehicle)) {
            return false;
        }

        if ((int) $vehicleSale->vehicle_id !== (int) $vehicle->id) {
            return false;
        }

        return (int) $vehicleSale->company_id === (int) $vehicle->company_id
            && (int) $vehicleSale->branch_id === (int) $vehicle->branch_id;
    }
}
