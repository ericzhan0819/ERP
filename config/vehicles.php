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
];

