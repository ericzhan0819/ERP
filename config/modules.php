<?php

/**
 * @deprecated 技術註解：此設定檔僅保留供過渡期參考，禁止作為 runtime module source。
 * 系統執行期的模組來源必須以 modules 資料表（與對應 seeder）為唯一真實來源，
 * 以避免前後端或多處設定漂移造成權限可見性不一致風險。
 */
return [
    // 技術註解：以下內容非 runtime 來源，不得再新增業務邏輯依賴。
    'dashboard' => [
        'key' => 'dashboard',
        'name' => '總覽',
        'route_name' => 'employee-system.overview',
        'permission' => 'module.dashboard.view',
        'is_active' => true,
    ],
    'test-module' => [
        'key' => 'test-module',
        'name' => '測試模塊',
        'route_name' => 'employee-system.test-module',
        'permission' => 'module.test-module.view',
        'is_active' => true,
    ],
];
