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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:Admin'])->prefix('staff-management')->group(function () {
    Route::get('/', [StaffManagementController::class, 'index'])->name('staff-management.index');
    Route::patch('/permissions', [StaffManagementController::class, 'updatePermissions'])->name('staff-management.update-permissions');
});

require __DIR__.'/auth.php';
