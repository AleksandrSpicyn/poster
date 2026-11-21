<?php

declare(strict_types=1);

$mode = env('TIKTOK_DEMO_MODE', 'sandbox');

return [
    'enabled' => env('TIKTOK_DEMO_ENABLED', false),
    'upload' => env('TIKTOK_DEMO_UPLOAD', false),
    'mode' => $mode,
    'key' => match ($mode) {
        'sandbox' => env('TIKTOK_DEMO_SANDBOX_KEY'),
        'production' => env('TIKTOK_CLIENT_ID'),
        default => null,
    },
    'secret' => match ($mode) {
        'sandbox' => env('TIKTOK_DEMO_SANDBOX_SECRET'),
        'production' => env('TIKTOK_CLIENT_SECRET'),
        default => null,
    },
    'redirect' => rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/demo/tiktok/callback',
];
