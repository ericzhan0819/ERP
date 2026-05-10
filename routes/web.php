<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffManagementController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/employee-system', function () {
    return Inertia::render('Dashboard/index');
})->middleware(['auth'])->name('employee-system.overview');

Route::get('/dashboard', function () {
    return redirect()->route('employee-system.overview');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/employee-system/inventory', function () {
        return Inertia::render('Inventory/Index');
    })->name('inventory.index');

    Route::get('/employee-system/orders', function () {
        return Inertia::render('Orders/Index');
    })->name('orders.index');
});

Route::middleware(['auth'])->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

});

Route::middleware(['auth', 'role:Admin'])
    // 技術註解：依新規則將非 dashboard 模組統一路徑收斂至 /employee-system/*。
    ->prefix('employee-system/staff-management')
    ->name('staff-management.')
    ->group(function () {
        Route::get('/', [StaffManagementController::class, 'index'])
            ->name('index');

        Route::patch('/users/{user}', [StaffManagementController::class, 'update'])
            ->name('update');
    });

require __DIR__.'/auth.php';
