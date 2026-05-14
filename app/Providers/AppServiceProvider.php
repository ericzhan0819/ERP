<?php

namespace App\Providers;

use App\Models\Vehicle;
use App\Policies\VehiclePolicy;
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

        Vite::prefetch(concurrency: 3);
    }
}
