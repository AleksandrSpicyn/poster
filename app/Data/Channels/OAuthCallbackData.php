<?php

declare(strict_types=1);

namespace App\Data\Channels;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class OAuthCallbackData extends Data
{
    public function __construct(
        #[Required, Max(128)] public string $state,
        #[Max(4096)] public ?string $code = null,
        #[Max(255)] public ?string $error = null,
    ) {}
}
