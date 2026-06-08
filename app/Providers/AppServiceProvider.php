<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\AccountingAccount;
use App\Models\AccountingEvent;
use App\Models\AccountingJournalEntry;
use App\Models\Vehicle;
use App\Models\VehicleCost;
use App\Models\VehicleSale;
use App\Models\VehicleSalePayment;
use App\Policies\CustomerPolicy;
use App\Policies\AccountingAccountPolicy;
use App\Policies\AccountingEventPolicy;
use App\Policies\AccountingJournalEntryPolicy;
use App\Policies\VehicleCostPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\VehicleSalePolicy;
use App\Policies\VehicleSalePaymentPolicy;
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
        // 技術註解：會計科目屬財務主檔，需顯式註冊 Policy 以確保所有敏感操作皆由後端授權層管控。
        Gate::policy(AccountingAccount::class, AccountingAccountPolicy::class);
        // 技術註解：會計事件只讀工作台仍屬財務候選資料，需顯式綁定 Policy 以維持 tenant scope 與檢視權限一致。
        Gate::policy(AccountingEvent::class, AccountingEventPolicy::class);
        // 技術註解：傳票草稿屬財務敏感資料，需顯式綁定 Policy，確保跨租戶與狀態限制由後端唯一控管。
        Gate::policy(AccountingJournalEntry::class, AccountingJournalEntryPolicy::class);
        // 技術註解：顯式註冊 CustomerPolicy，確保客戶主檔與敏感個資皆由後端授權層控管。
        Gate::policy(Customer::class, CustomerPolicy::class);
        // 技術註解：明確註冊 VehiclePolicy，確保後端授權為唯一真實來源，避免遺漏造成 IDOR 風險。
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        // 技術註解：成本操作屬財務敏感路徑，需顯式綁定 Policy，避免授權漏接造成越權風險。
        Gate::policy(VehicleCost::class, VehicleCostPolicy::class);
        // 技術註解：銷售操作包含客戶、成交價與佣金資訊，需顯式綁定 Policy 避免敏感資料越權存取。
        Gate::policy(VehicleSale::class, VehicleSalePolicy::class);
        // 技術註解：收款操作屬銷售財務敏感資料，需搭配 tenant scoped query 與顯式 Policy 控制。
        Gate::policy(VehicleSalePayment::class, VehicleSalePaymentPolicy::class);

        Vite::prefetch(concurrency: 3);
    }
}
