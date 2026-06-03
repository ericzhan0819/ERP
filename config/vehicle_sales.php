<?php

return [
    // 技術註解：銷售狀態白名單由後端集中管理，避免前端任意值破壞車輛生命週期同步規則。
    'sale_statuses' => [
        'draft' => '草稿',
        'reserved' => '保留',
        'sold' => '成交',
        'cancelled' => '取消',
    ],
];
