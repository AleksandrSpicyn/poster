<?php

declare(strict_types=1);

return [
    'enabled' => env('TIKTOK_DEMO_ENABLED', false),
    'upload' => env('TIKTOK_DEMO_UPLOAD', false),
    'key' => env('TIKTOK_DEMO_SANDBOX_KEY'),
    'secret' => env('TIKTOK_DEMO_SANDBOX_SECRET'),
    'redirect' => env('TIKTOK_DEMO_REDIRECT_URI'),
    'authors' => [
        'author-a' => env('TIKTOK_DEMO_AUTHOR_A_HASH'),
        'author-b' => env('TIKTOK_DEMO_AUTHOR_B_HASH'),
    ],
];
