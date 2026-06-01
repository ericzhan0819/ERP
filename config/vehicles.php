<?php

return [
    // 技術註解：集中生命週期白名單，避免前後端各自硬編碼造成驗證與顯示不一致。
    'lifecycle_statuses' => [
        'draft' => '草稿',
        'in_stock' => '在庫',
        'reserved' => '保留',
        'sold' => '已售',
        'archived' => '封存',
    ],

    // 技術註解：車輛成本類型白名單由後端集中管理，避免前端任意輸入造成報表口徑不一致。
    'vehicle_cost_types' => [
        'purchase_price' => '採購價',
        'repair' => '維修',
        'detailing' => '美容整備',
        'tax' => '稅費',
        'transport' => '運輸',
        'inspection' => '檢驗',
        'management' => '管理費',
        'other' => '其他',
    ],

    // 技術註解：付款狀態白名單需固定鍵值，確保後端統計與稽核欄位語意一致。
    'vehicle_cost_payment_statuses' => [
        'unpaid' => '未付款',
        'partially_paid' => '部分付款',
        'paid' => '已付款',
    ],
];
