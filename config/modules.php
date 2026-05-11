<?php

return [
    // 技術註解：模組清單是後端權限過濾的唯一設定來源，維持最小可驗證範圍。
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
