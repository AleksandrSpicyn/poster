<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ResetPasswordData extends Data
{
    public function __construct(
        #[Required, Email]
        public string $email,
        #[Required, Confirmed, Password(default: true)]
        public string $password,
        #[Required]
        public string $token,
    ) {}
}
