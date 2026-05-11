<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\StaffPermissionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // 技術註解：純 UI Demo 首頁不讀取認證狀態，維持透明且可預期的展示入口。
    return Inertia::render('Welcome');
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

Route::get('/dashboard', function () {
    // 技術註解：保留舊入口相容性，導向新的純展示主控台路徑。
    return redirect('/employee-system');
})->name('dashboard');
