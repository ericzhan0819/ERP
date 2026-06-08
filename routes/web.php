<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AccountingAccountController;
use App\Http\Controllers\AccountingJournalEntryController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\StaffPermissionController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleCostController;
use App\Http\Controllers\VehicleCostManagementController;
use App\Http\Controllers\VehicleSaleController;
use App\Http\Controllers\VehicleSalePaymentController;
use App\Services\CompanyBrandService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // 技術註解：未登入首頁需使用公開品牌資料來源，避免依賴 auth shared props。
    return Inertia::render('Welcome', [
        'brand' => app(CompanyBrandService::class)->resolveForPublic(),
    ]);
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/employee-system', function () {
    // 技術註解：Dashboard 存取交由 module.access 統一檢查，不在路由閉包接觸權限 API。
    return Inertia::render('Dashboard/index');
})->middleware(['auth', 'module.access:dashboard'])->name('employee-system.overview');

Route::get('/employee-system/test-module', function () {
    // 技術註解：測試模組只驗證 RBAC 門禁，不建立 CRUD 或管理後台。
    return Inertia::render('TestModule');
})->middleware(['auth', 'module.access:test-module'])->name('employee-system.test-module');

Route::get('/employee-system/staff-permissions', [StaffPermissionController::class, 'index'])
    ->middleware(['auth', 'module.access:staff-permission'])
    ->name('employee-system.staff-permissions.index');

Route::patch('/employee-system/staff-permissions/{user}/roles', [StaffPermissionController::class, 'updateRoles'])
    ->middleware(['auth', 'permission:staff-permission.update-role'])
    ->name('employee-system.staff-permissions.roles.update');

Route::patch('/employee-system/staff-permissions/{user}/permissions', [StaffPermissionController::class, 'updatePermissions'])
    ->middleware(['auth', 'permission:staff-permission.update-permission'])
    ->name('employee-system.staff-permissions.permissions.update');

Route::patch('/employee-system/staff-permissions/roles/{role}/permissions', [StaffPermissionController::class, 'updateRolePermissions'])
    ->middleware(['auth', 'permission:staff-permission.update-permission'])
    ->name('employee-system.staff-permissions.roles.permissions.update');

Route::patch('/employee-system/staff-permissions/roles/{role}/meta', [StaffPermissionController::class, 'updateRoleMeta'])
    ->middleware(['auth', 'permission:staff-permission.update-role'])
    ->name('employee-system.staff-permissions.roles.update.meta');

Route::post('/employee-system/staff-permissions/roles', [StaffPermissionController::class, 'createRole'])
    ->middleware(['auth', 'permission:staff-permission.update-role'])
    ->name('employee-system.staff-permissions.roles.store');

Route::delete('/employee-system/staff-permissions/roles/{role}', [StaffPermissionController::class, 'deleteRole'])
    ->middleware(['auth', 'permission:staff-permission.update-role'])
    ->name('employee-system.staff-permissions.roles.destroy');

Route::get('/employee-system/vehicles', [VehicleController::class, 'index'])
    // 技術註解：車輛模組門禁統一採用複數 vehicles key，對齊現行 RBAC/Seeder 命名，降低單數舊名造成授權漂移風險。
    ->middleware(['auth', 'module.access:vehicles'])
    ->name('employee-system.vehicles.index');

Route::get('/employee-system/vehicles/create', [VehicleController::class, 'create'])
    // 技術註解：建立頁同樣受 vehicles 模組門禁保護，避免繞過側邊欄直接 URL 進入。
    ->middleware(['auth', 'module.access:vehicles'])
    ->name('employee-system.vehicles.create');

Route::post('/employee-system/vehicles', [VehicleController::class, 'store'])
    // 技術註解：寫入操作維持在 auth + module.access 下，細部授權由 controller/policy 負責。
    ->middleware(['auth', 'module.access:vehicles'])
    ->name('employee-system.vehicles.store');

Route::get('/employee-system/vehicles/{vehicle}', [VehicleController::class, 'show'])
    // 技術註解：明細頁與列表頁使用同一模組門禁，避免直接 URL 存取繞過前端可見性控制。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->name('employee-system.vehicles.show');

Route::get('/employee-system/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])
    // 技術註解：編輯頁需與 show 一致套用模組門禁，並搭配數字限制降低惡意參數噪音。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->name('employee-system.vehicles.edit');

Route::get('/employee-system/vehicle-costs', [VehicleCostManagementController::class, 'index'])
    // 技術註解：獨立成本入口使用 vehicle-costs module key；實際權限仍由 module registry 對應到既有 costs.view，避免新增權限命名造成 RBAC 漂移。
    ->middleware(['auth', 'module.access:vehicle-costs'])
    ->name('employee-system.vehicle-costs.index');

Route::get('/employee-system/vehicle-costs/create', [VehicleCostManagementController::class, 'create'])
    // 技術註解：獨立新增工作台只提供頁面入口，寫入仍沿用既有 vehicle-scoped mutation，避免新增另一套成本寫入邏輯。
    ->middleware(['auth', 'module.access:vehicle-costs'])
    ->name('employee-system.vehicle-costs.create');

Route::get('/employee-system/vehicle-costs/{vehicleCost}/edit', [VehicleCostManagementController::class, 'edit'])
    // 技術註解：不使用 implicit binding，Controller 會 tenant scoped 查詢成本與車輛以降低 IDOR 資訊探測。
    ->middleware(['auth', 'module.access:vehicle-costs'])
    ->whereNumber('vehicleCost')
    ->name('employee-system.vehicle-costs.edit');

Route::patch('/employee-system/vehicles/{vehicle}', [VehicleController::class, 'update'])
    // 技術註解：更新路由不使用 implicit model binding，避免未 scoped 查詢造成跨租戶 IDOR。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->name('employee-system.vehicles.update');

Route::get('/employee-system/customers', [CustomerController::class, 'index'])
    // 技術註解：客戶模組採獨立 module.access:customers，不與車輛或銷售權限混用，避免權限邊界擴張。
    ->middleware(['auth', 'module.access:customers'])
    ->name('employee-system.customers.index');

Route::get('/employee-system/customers/create', [CustomerController::class, 'create'])
    // 技術註解：建立頁同樣受 customers 模組門禁保護，細部 create 授權交由 Policy/FormRequest。
    ->middleware(['auth', 'module.access:customers'])
    ->name('employee-system.customers.create');

Route::post('/employee-system/customers', [CustomerController::class, 'store'])
    // 技術註解：寫入路由保留 module access 與 request/policy 雙層授權，避免直接 URL 越權建立客戶。
    ->middleware(['auth', 'module.access:customers'])
    ->name('employee-system.customers.store');

Route::get('/employee-system/customers/{customer}', [CustomerController::class, 'show'])
    // 技術註解：不使用 implicit binding，Controller 先 scoped 查詢再授權，跨 tenant 優先 404。
    ->middleware(['auth', 'module.access:customers'])
    ->whereNumber('customer')
    ->name('employee-system.customers.show');

Route::get('/employee-system/customers/{customer}/edit', [CustomerController::class, 'edit'])
    // 技術註解：編輯頁與詳情頁一致採 scoped 查詢，避免透過 ID 探測他租戶客戶資料。
    ->middleware(['auth', 'module.access:customers'])
    ->whereNumber('customer')
    ->name('employee-system.customers.edit');

Route::patch('/employee-system/customers/{customer}', [CustomerController::class, 'update'])
    // 技術註解：更新路由不接收任何租戶或流水號欄位，後端以 scoped query 防止 IDOR。
    ->middleware(['auth', 'module.access:customers'])
    ->whereNumber('customer')
    ->name('employee-system.customers.update');

Route::post('/employee-system/vehicles/{vehicle}/costs', [VehicleCostController::class, 'store'])
    // 技術註解：成本建立路由維持在 vehicles 模組門禁下，細部授權由 policy 執行，避免前端判斷被誤當安全機制。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->name('employee-system.vehicles.costs.store');

Route::patch('/employee-system/vehicles/{vehicle}/costs/{vehicleCost}', [VehicleCostController::class, 'update'])
    // 技術註解：成本更新使用數字約束並保留後端 tenant scoped 查詢，降低 IDOR 嘗試面。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->whereNumber('vehicleCost')
    ->name('employee-system.vehicles.costs.update');

Route::post('/employee-system/vehicles/{vehicle}/sales', [VehicleSaleController::class, 'store'])
    // 技術註解：銷售建立路由掛在 vehicles 模組門禁下，細部銷售權限與 tenant 邊界由 controller/policy 執行。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->name('employee-system.vehicles.sales.store');

Route::patch('/employee-system/vehicles/{vehicle}/sales/{vehicleSale}', [VehicleSaleController::class, 'update'])
    // 技術註解：銷售更新使用數字約束並保留後端 tenant scoped 查詢，降低跨車與跨租戶 IDOR 嘗試面。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->whereNumber('vehicleSale')
    ->name('employee-system.vehicles.sales.update');

Route::patch('/employee-system/vehicles/{vehicle}/sales/{vehicleSale}/complete', [VehicleSaleController::class, 'complete'])
    // 技術註解：交易完成屬 Vehicle Sale domain，維持 vehicles 模組門禁並由 policy 檢查 completion.confirm，避免與 Receivables mark-sold 權限混用。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->whereNumber('vehicleSale')
    ->name('employee-system.vehicles.sales.complete');

Route::post('/employee-system/vehicles/{vehicle}/sales/{vehicleSale}/payments', [VehicleSalePaymentController::class, 'store'])
    // 技術註解：收款建立掛於銷售底下，Controller 先 tenant scoped vehicle/sale 後授權，避免跨車收款注入。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->whereNumber('vehicleSale')
    ->name('employee-system.vehicles.sales.payments.store');

Route::patch('/employee-system/vehicles/{vehicle}/sales/{vehicleSale}/payments/{vehicleSalePayment}/void', [VehicleSalePaymentController::class, 'void'])
    // 技術註解：收款不可刪除，僅提供作廢並保留完整審計軌跡。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->whereNumber('vehicleSale')
    ->whereNumber('vehicleSalePayment')
    ->name('employee-system.vehicles.sales.payments.void');

Route::get('/employee-system/receivables', [ReceivableController::class, 'index'])
    // 技術註解：收款管理使用獨立 receivables 模組門禁，避免沿用車輛頁舊收款權限造成入口邊界混淆。
    ->middleware(['auth', 'module.access:receivables'])
    ->name('employee-system.receivables.index');

Route::get('/employee-system/receivables/{vehicleSale}', [ReceivableController::class, 'show'])
    // 技術註解：不使用 implicit model binding，Controller 會先套用 company/branch scope，跨租戶一律 404。
    ->middleware(['auth', 'module.access:receivables'])
    ->whereNumber('vehicleSale')
    ->name('employee-system.receivables.show');

Route::patch('/employee-system/receivables/{vehicleSale}/mark-sold', [ReceivableController::class, 'markSold'])
    // 技術註解：成交標記屬敏感狀態轉換，保留 receivables 模組門禁並由 Controller 二次檢查獨立權限以防越權。
    ->middleware(['auth', 'module.access:receivables'])
    ->whereNumber('vehicleSale')
    ->name('employee-system.receivables.mark-sold');

Route::post('/employee-system/receivables/{vehicleSale}/payments', [ReceivableController::class, 'storePayment'])
    // 技術註解：收款新增需同時通過 module.access 與 controller 內 create 權限，避免僅有 view 者可寫入。
    ->middleware(['auth', 'module.access:receivables'])
    ->whereNumber('vehicleSale')
    ->name('employee-system.receivables.payments.store');

Route::patch('/employee-system/receivables/{vehicleSale}/payments/{vehicleSalePayment}/void', [ReceivableController::class, 'voidPayment'])
    // 技術註解：作廢保留原紀錄與 audit log，不提供刪除或退款流程。
    ->middleware(['auth', 'module.access:receivables'])
    ->whereNumber('vehicleSale')
    ->whereNumber('vehicleSalePayment')
    ->name('employee-system.receivables.payments.void');

Route::get('/employee-system/accounting/accounts', [AccountingAccountController::class, 'index'])
    ->middleware(['auth', 'module.access:accounting-accounts'])
    ->name('employee-system.accounting.accounts.index');

Route::get('/employee-system/accounting/accounts/create', [AccountingAccountController::class, 'create'])
    ->middleware(['auth', 'module.access:accounting-accounts'])
    ->name('employee-system.accounting.accounts.create');

Route::post('/employee-system/accounting/accounts', [AccountingAccountController::class, 'store'])
    ->middleware(['auth', 'module.access:accounting-accounts'])
    ->name('employee-system.accounting.accounts.store');

Route::get('/employee-system/accounting/accounts/{account}/edit', [AccountingAccountController::class, 'edit'])
    ->middleware(['auth', 'module.access:accounting-accounts'])
    ->whereNumber('account')
    ->name('employee-system.accounting.accounts.edit');

Route::patch('/employee-system/accounting/accounts/{account}', [AccountingAccountController::class, 'update'])
    ->middleware(['auth', 'module.access:accounting-accounts'])
    ->whereNumber('account')
    ->name('employee-system.accounting.accounts.update');

Route::get('/employee-system/accounting/journal-entries', [AccountingJournalEntryController::class, 'index'])
    ->middleware(['auth', 'module.access:accounting-journals'])
    ->name('employee-system.accounting.journal-entries.index');

Route::get('/employee-system/accounting/journal-entries/create', [AccountingJournalEntryController::class, 'create'])
    ->middleware(['auth', 'module.access:accounting-journals'])
    ->name('employee-system.accounting.journal-entries.create');

Route::post('/employee-system/accounting/journal-entries', [AccountingJournalEntryController::class, 'store'])
    ->middleware(['auth', 'module.access:accounting-journals'])
    ->name('employee-system.accounting.journal-entries.store');

Route::get('/employee-system/accounting/journal-entries/{journalEntry}', [AccountingJournalEntryController::class, 'show'])
    ->middleware(['auth', 'module.access:accounting-journals'])
    ->whereNumber('journalEntry')
    ->name('employee-system.accounting.journal-entries.show');

Route::get('/employee-system/accounting/journal-entries/{journalEntry}/edit', [AccountingJournalEntryController::class, 'edit'])
    ->middleware(['auth', 'module.access:accounting-journals'])
    ->whereNumber('journalEntry')
    ->name('employee-system.accounting.journal-entries.edit');

Route::patch('/employee-system/accounting/journal-entries/{journalEntry}', [AccountingJournalEntryController::class, 'update'])
    ->middleware(['auth', 'module.access:accounting-journals'])
    ->whereNumber('journalEntry')
    ->name('employee-system.accounting.journal-entries.update');

Route::patch('/employee-system/accounting/journal-entries/{journalEntry}/post', [AccountingJournalEntryController::class, 'post'])
    ->middleware(['auth', 'module.access:accounting-journals'])
    ->whereNumber('journalEntry')
    ->name('employee-system.accounting.journal-entries.post');

Route::patch('/employee-system/accounting/journal-entries/{journalEntry}/void', [AccountingJournalEntryController::class, 'void'])
    ->middleware(['auth', 'module.access:accounting-journals'])
    ->whereNumber('journalEntry')
    ->name('employee-system.accounting.journal-entries.void');

Route::get('/employee-system/audit/activity-logs', [AuditLogController::class, 'activityLogs'])
    ->middleware(['auth', 'module.access:audit'])
    ->name('employee-system.audit.activity-logs');

Route::get('/employee-system/audit/login-logs', [AuditLogController::class, 'loginLogs'])
    ->middleware(['auth', 'module.access:audit'])
    ->name('employee-system.audit.login-logs');

Route::get('/employee-system/profile', [ProfileController::class, 'edit'])
    ->middleware('auth')
    ->name('employee-system.profile.edit');

Route::patch('/employee-system/profile', [ProfileController::class, 'update'])
    ->middleware('auth')
    ->name('employee-system.profile.update');

Route::put('/employee-system/profile/password', [ProfileController::class, 'updatePassword'])
    ->middleware('auth')
    ->name('employee-system.profile.password.update');

Route::get('/employee-system/company-settings', [CompanySettingController::class, 'edit'])
    ->middleware(['auth', 'module.access:company-settings'])
    ->name('employee-system.company-settings.edit');

Route::put('/employee-system/company-settings', [CompanySettingController::class, 'update'])
    ->middleware(['auth', 'permission:module.company-settings.update'])
    ->name('employee-system.company-settings.update');

Route::get('/dashboard', function () {
    // 技術註解：保留舊入口相容性，導向新的純展示主控台路徑。
    return redirect('/employee-system');
})->name('dashboard');
