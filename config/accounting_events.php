<?php

return [
    'source_types' => [
        'vehicle_sale_completion' => '車輛交易完成',
        'vehicle_sale_payment' => '車輛銷售收款',
        'vehicle_cost' => '車輛成本',
        'manual_adjustment' => '人工會計調整',
    ],

    'event_types' => [
        'vehicle_sale_completed' => '車輛交易完成',
        'payment_received' => '收到款項',
        'vehicle_cost_recorded' => '記錄車輛成本',
        'manual_accounting_review' => '人工會計覆核',
    ],

    'statuses' => [
        'pending' => '待覆核',
        'reviewed' => '已覆核',
        'converted' => '已轉傳票草稿',
        'voided' => '已作廢',
    ],
];
