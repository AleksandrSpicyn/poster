<?php

declare(strict_types=1);

return [
    'default_for_images' => 'codex',

    'providers' => [
        'codex' => [
            'driver' => 'codex',
            'key' => '',
        ],
        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],
    ],
];
