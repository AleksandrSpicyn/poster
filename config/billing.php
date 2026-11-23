<?php

declare(strict_types=1);

use App\Classes\Billing\YooKassa\YooKassaGateway;

return [
    'default_merchant' => env('BILLING_MERCHANT', 'sandbox'),
    'merchants' => [
        'sandbox' => [
            'provider' => 'yookassa', 'shop' => env('YOOKASSA_TEST_SHOP_ID'), 'test_mode' => true,
            'credentials' => ['secret' => env('YOOKASSA_TEST_SECRET')],
        ],
        'production' => [
            'provider' => 'yookassa', 'shop' => env('YOOKASSA_SHOP_ID'), 'test_mode' => false,
            'credentials' => ['secret' => env('YOOKASSA_SECRET')],
        ],
    ],
    'drivers' => [
        'yookassa' => ['payments' => YooKassaGateway::class, 'refunds' => YooKassaGateway::class],
    ],
];
