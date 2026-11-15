<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Bail;
use Spatie\LaravelData\Attributes\Validation\CurrentPassword;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class DeleteProfileData extends Data
{
    public function __construct(
        #[Bail, Required, CurrentPassword]
        public string $password,
    ) {}
}
