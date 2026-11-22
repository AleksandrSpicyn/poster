<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ClippingKeyData extends Data
{
    public function __construct(#[Required, Max(4096)] public string $api_key) {}

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'api_key.required' => 'Введите новый ключ API.',
            'api_key.max' => 'Ключ API не должен быть длиннее 4096 символов.',
            'api_key.string' => 'Введите новый ключ API.',
        ];
    }
}
