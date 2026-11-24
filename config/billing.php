<?php

declare(strict_types=1);

use App\Classes\Billing\YooKassa\YooKassaGateway;

return [
    'offer_url' => env('BILLING_OFFER_URL'),
    'live_sales' => env('BILLING_LIVE_SALES', false),
    'fiscal' => ['vat' => env('BILLING_RECEIPT_VAT_CODE'), 'subject' => env('BILLING_RECEIPT_SUBJECT'), 'mode' => env('BILLING_RECEIPT_MODE'), 'tax_system' => env('BILLING_RECEIPT_TAX_SYSTEM')],
    'operators' => array_filter(array_map(trim(...), explode(',', (string) env('BILLING_OPERATOR_EMAILS', '')))),
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
        'yookassa' => ['payments' => YooKassaGateway::class, 'refunds' => YooKassaGateway::class, 'receipts' => YooKassaGateway::class],
    ],
];
