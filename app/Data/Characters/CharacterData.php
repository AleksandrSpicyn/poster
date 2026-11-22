<?php

declare(strict_types=1);

namespace App\Data\Characters;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CharacterData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Min(0)]
        public ?int $lock_version = null,
        #[Max(2000)]
        public ?string $story_role = null,
    ) {}
}
