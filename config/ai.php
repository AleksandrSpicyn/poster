<?php

declare(strict_types=1);

return [
    'default' => 'codex',
    'default_for_images' => 'codex',

    'providers' => [
        'codex' => [
            'driver' => 'codex',
            'key' => '',
            'model' => env('CODEX_MODEL', 'gpt-6.1-sol'),
        ],
        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],
    ],
];
