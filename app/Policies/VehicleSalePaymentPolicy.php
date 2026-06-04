<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;

class VehicleSalePaymentPolicy
{
    public function viewAny(User $user, VehicleSale $vehicleSale, Vehicle $vehicle): bool
    {
        return $user->can('module.vehicles.sales.payments.view')
            && $this->isSameTenantWithSale($user, $vehicleSale, $vehicle);
    }

    public function view(User $user, VehicleSalePayment $payment, VehicleSale $vehicleSale, Vehicle $vehicle): bool
    {
        return $user->can('module.vehicles.sales.payments.view')
            && $this->isSameTenantWithPayment($user, $payment, $vehicleSale, $vehicle);
    }

    public function create(User $user, VehicleSale $vehicleSale, Vehicle $vehicle): bool
    {
        return $user->can('module.vehicles.sales.payments.create')
            && $this->isSameTenantWithSale($user, $vehicleSale, $vehicle);
    }

    public function void(User $user, VehicleSalePayment $payment, VehicleSale $vehicleSale, Vehicle $vehicle): bool
    {
        return $user->can('module.vehicles.sales.payments.void')
            && $this->isSameTenantWithPayment($user, $payment, $vehicleSale, $vehicle);
    }

    private function isSameTenantWithSale(User $user, VehicleSale $vehicleSale, Vehicle $vehicle): bool
    {
        $userCompanyId = (int) ($user->company_id ?? 0);
        if ($userCompanyId <= 0 || $userCompanyId !== (int) $vehicle->company_id) {
            return false;
        }

        if ($user->branch_id !== null && (int) $user->branch_id !== (int) $vehicle->branch_id) {
            return false;
        }

        return (int) $vehicleSale->vehicle_id === (int) $vehicle->id
            && (int) $vehicleSale->company_id === (int) $vehicle->company_id
            && (int) $vehicleSale->branch_id === (int) $vehicle->branch_id;
    }

    private function isSameTenantWithPayment(User $user, VehicleSalePayment $payment, VehicleSale $vehicleSale, Vehicle $vehicle): bool
    {
        return $this->isSameTenantWithSale($user, $vehicleSale, $vehicle)
            && (int) $payment->vehicle_id === (int) $vehicle->id
            && (int) $payment->vehicle_sale_id === (int) $vehicleSale->id
            && (int) $payment->company_id === (int) $vehicle->company_id
            && (int) $payment->branch_id === (int) $vehicle->branch_id;
    }
}