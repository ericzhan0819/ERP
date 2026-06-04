<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleSalePaymentRequest;
use App\Http\Requests\VoidVehicleSalePaymentRequest;
use App\Models\Vehicle;
use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;
use App\Services\AuditLogService;
use App\Services\VehicleSalePaymentNumberService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class VehicleSalePaymentController extends Controller
{
    public function __construct(
        private readonly VehicleSalePaymentNumberService $paymentNumberService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function store(StoreVehicleSalePaymentRequest $request, int $vehicle, int $vehicleSale)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $foundVehicle = $this->scopedVehicleQuery($user)->whereKey($vehicle)->firstOrFail();
        $foundSale = $this->scopedVehicleSaleQuery($user)->where('vehicle_id', $foundVehicle->id)->whereKey($vehicleSale)->firstOrFail();

        $this->authorize('create', [VehicleSalePayment::class, $foundSale, $foundVehicle]);

        $payment = DB::transaction(function () use ($request, $user, $foundVehicle, $foundSale): VehicleSalePayment {
            $validated = $request->validated();
            $created = VehicleSalePayment::create([
                'company_id' => (int) $foundSale->company_id,
                'branch_id' => (int) $foundSale->branch_id,
                'vehicle_id' => (int) $foundVehicle->id,
                'vehicle_sale_id' => (int) $foundSale->id,
                'customer_id' => $foundSale->customer_id,
                'payment_number' => $this->paymentNumberService->generate((int) $foundSale->company_id),
                'payment_type' => $validated['payment_type'],
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'paid_at' => $validated['paid_at'] ?? null,
                'reference_no' => $validated['reference_no'] ?? null,
                'status' => 'received',
                'notes' => $validated['notes'] ?? null,
                'created_by' => (int) $user->id,
                'updated_by' => (int) $user->id,
            ]);

            $this->auditLogService->log(
                actor: $user,
                action: 'vehicle_sale_payment.created',
                description: 'Vehicle sale payment created',
                targetUser: null,
                metadata: ['module' => 'vehicles'],
                subject: $created,
                oldValues: null,
                newValues: $this->buildCreatedAuditValues($created),
                request: $request,
                event: 'vehicle_sale_payment.created',
            );

            return $created;
        });

        return redirect()->route('employee-system.vehicles.edit', $payment->vehicle_id);
    }

    public function void(VoidVehicleSalePaymentRequest $request, int $vehicle, int $vehicleSale, int $vehicleSalePayment)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $foundVehicle = $this->scopedVehicleQuery($user)->whereKey($vehicle)->firstOrFail();
        $foundSale = $this->scopedVehicleSaleQuery($user)->where('vehicle_id', $foundVehicle->id)->whereKey($vehicleSale)->firstOrFail();
        $payment = $this->scopedVehicleSalePaymentQuery($user)
            ->where('vehicle_id', $foundVehicle->id)
            ->where('vehicle_sale_id', $foundSale->id)
            ->whereKey($vehicleSalePayment)
            ->firstOrFail();

        $this->authorize('void', [$payment, $foundSale, $foundVehicle]);

        if ($payment->status !== 'received') {
            throw new HttpResponseException(response()->json(['message' => '僅可作廢已收款紀錄。'], 422));
        }

        DB::transaction(function () use ($request, $user, $payment): void {
            $oldValues = $this->buildVoidedAuditValues($payment);
            $payment->update([
                'status' => 'voided',
                'voided_by' => (int) $user->id,
                'voided_at' => now(),
                'void_reason' => $request->validated('void_reason'),
                'updated_by' => (int) $user->id,
            ]);

            $this->auditLogService->log(
                actor: $user,
                action: 'vehicle_sale_payment.voided',
                description: 'Vehicle sale payment voided',
                targetUser: null,
                metadata: ['module' => 'vehicles'],
                subject: $payment,
                oldValues: $oldValues,
                newValues: $this->buildVoidedAuditValues($payment->fresh()),
                request: $request,
                event: 'vehicle_sale_payment.voided',
            );
        });

        return redirect()->route('employee-system.vehicles.edit', $foundVehicle->id);
    }

    private function scopedVehicleQuery(?Authenticatable $user): Builder
    {
        $query = Vehicle::query()->where('company_id', (int) ($user?->company_id ?? 0));
        if ($user?->branch_id !== null) { $query->where('branch_id', (int) $user->branch_id); }
        return $query;
    }

    private function scopedVehicleSaleQuery(?Authenticatable $user): Builder
    {
        $query = VehicleSale::query()->where('company_id', (int) ($user?->company_id ?? 0));
        if ($user?->branch_id !== null) { $query->where('branch_id', (int) $user->branch_id); }
        return $query;
    }

    private function scopedVehicleSalePaymentQuery(?Authenticatable $user): Builder
    {
        $query = VehicleSalePayment::query()->where('company_id', (int) ($user?->company_id ?? 0));
        if ($user?->branch_id !== null) { $query->where('branch_id', (int) $user->branch_id); }
        return $query;
    }

    /** @return array<string, mixed> */
    private function buildCreatedAuditValues(VehicleSalePayment $payment): array
    {
        return [
            'payment_number' => $payment->payment_number,
            'vehicle_sale_id' => $payment->vehicle_sale_id,
            'customer_id' => $payment->customer_id,
            'payment_type' => $payment->payment_type,
            'payment_method' => $payment->payment_method,
            'amount' => $payment->amount,
            'paid_at' => optional($payment->paid_at)->format('Y-m-d'),
            'reference_no' => $payment->reference_no,
            'status' => $payment->status,
            'notes' => $payment->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function buildVoidedAuditValues(?VehicleSalePayment $payment): array
    {
        return [
            'payment_number' => $payment?->payment_number,
            'status' => $payment?->status,
            'voided_at' => optional($payment?->voided_at)->format('Y-m-d H:i:s'),
            'void_reason' => $payment?->void_reason,
        ];
    }
}