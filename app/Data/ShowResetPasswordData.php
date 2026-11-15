<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ShowResetPasswordData extends Data
{
    public function __construct(
        #[FromRouteParameter('token'), Required]
        public string $token,
        public ?string $email = null,
    ) {}
}
