<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Bail;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\CurrentPassword;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class UpdatePasswordData extends Data
{
    public function __construct(
        #[Bail, Required, CurrentPassword]
        public string $current_password,
        #[Required, Confirmed, Password(default: true)]
        public string $password,
    ) {}
}
