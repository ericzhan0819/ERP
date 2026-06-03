<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffPermissionController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleCostController;
use App\Http\Controllers\VehicleSaleController;
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

Route::patch('/employee-system/vehicles/{vehicle}', [VehicleController::class, 'update'])
    // 技術註解：更新路由不使用 implicit model binding，避免未 scoped 查詢造成跨租戶 IDOR。
    ->middleware(['auth', 'module.access:vehicles'])
    ->whereNumber('vehicle')
    ->name('employee-system.vehicles.update');

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
