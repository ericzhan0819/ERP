<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    /**
     * 技術註解：列表先授權 viewAny，再套用 tenant 範圍查詢，避免跨公司或跨分店資料外洩。
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = $this->scopedVehicleQuery($request->user())
            ->orderByDesc('id')
            ->get([
                'id',
                'company_id',
                'branch_id',
                'stock_number',
                'vin',
                'brand',
                'model',
                'model_year',
                'lifecycle_status',
            ]);

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * 技術註解：禁止未 scoped implicit binding，必須先套用 tenant 查詢，查無資料直接 404。
     */
    public function show(Request $request, int $vehicle): Response
    {
        $foundVehicle = $this->scopedVehicleQuery($request->user())
            ->whereKey($vehicle)
            ->firstOrFail();

        // 技術註解：查到資料後仍需再次經過 policy，防止權限提升或未預期授權繞過。
        $this->authorize('view', $foundVehicle);

        return Inertia::render('Vehicles/Show', [
            'vehicle' => [
                'id' => $foundVehicle->id,
                'company_id' => $foundVehicle->company_id,
                'branch_id' => $foundVehicle->branch_id,
                'stock_number' => $foundVehicle->stock_number,
                'vin' => $foundVehicle->vin,
                'brand' => $foundVehicle->brand,
                'model' => $foundVehicle->model,
                'model_year' => $foundVehicle->model_year,
                'lifecycle_status' => $foundVehicle->lifecycle_status,
            ],
        ]);
    }

    /**
     * 技術註解：集中 tenant 範圍，先 company 再 branch，避免 IDOR 與跨邊界讀取。
     */
    private function scopedVehicleQuery(?Authenticatable $user): Builder
    {
        /** @var \App\Models\User $user */
        $userCompanyId = (int) ($user?->company_id ?? 0);
        $userBranchId = $user?->branch_id;

        $query = Vehicle::query()->where('company_id', $userCompanyId);

        if ($userBranchId !== null) {
            $query->where('branch_id', (int) $userBranchId);
        }

        return $query;
    }
}
