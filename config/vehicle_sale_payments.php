<?php

return [
    'payment_types' => [
        'deposit' => '訂金',
        'balance' => '尾款',
        'other' => '其他',
    ],

    'payment_methods' => [
        'cash' => '現金',
        'bank_transfer' => '匯款',
        'credit_card' => '信用卡',
        'line_pay' => 'LINE Pay',
        'other' => '其他',
    ],

    'statuses' => [
        'received' => '已收款',
        'voided' => '已作廢',
    ],

    'receivable_statuses' => [
        'unpaid' => '未收款',
        'partial' => '部分收款',
        'paid' => '已收清',
        'overpaid' => '超收',
    ],
];