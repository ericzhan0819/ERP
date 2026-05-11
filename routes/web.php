<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // 技術註解：純 UI Demo 首頁不讀取認證狀態，維持透明且可預期的展示入口。
    return Inertia::render('Welcome');
});

Route::get('/login', function () {
    // 技術註解：登入頁僅作前端流程展示，不連接後端 Auth guard。
    return Inertia::render('Auth/Login');
})->name('login');

Route::get('/employee-system', function () {
    // 技術註解：Employee System 固定進入 Dashboard demo，避免權限資料造成展示阻斷。
    return Inertia::render('Dashboard/index');
})->name('employee-system.overview');

Route::get('/dashboard', function () {
    // 技術註解：保留舊入口相容性，導向新的純展示主控台路徑。
    return redirect('/employee-system');
})->name('dashboard');
