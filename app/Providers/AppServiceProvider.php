<?php

namespace App\Providers;

use App\Models\Vehicle;
use App\Models\VehicleCost;
use App\Policies\VehiclePolicy;
use App\Policies\VehicleCostPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 技術註解：明確註冊 VehiclePolicy，確保後端授權為唯一真實來源，避免遺漏造成 IDOR 風險。
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        // 技術註解：成本操作屬財務敏感路徑，需顯式綁定 Policy，避免授權漏接造成越權風險。
        Gate::policy(VehicleCost::class, VehicleCostPolicy::class);

        Vite::prefetch(concurrency: 3);
    }
}
