<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Lowercase;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\References\AuthenticatedUserReference;

class UpdateProfileData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required, Lowercase, Email, Max(255), Unique(User::class, ignore: new AuthenticatedUserReference())]
        public string $email,
    ) {}
}
