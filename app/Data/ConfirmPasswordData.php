<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ConfirmPasswordData extends Data
{
    public function __construct(
        #[Required]
        public string $password,
    ) {}
}
