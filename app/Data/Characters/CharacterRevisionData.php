<?php

declare(strict_types=1);

namespace App\Data\Characters;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CharacterRevisionData extends Data
{
    /**
     * @param array<string, mixed> $appearance
     * @param array<string, mixed> $voice
     */
    public function __construct(
        #[Required, Max(10000)]
        public string $description,
        #[Max(100)]
        public array $appearance = [],
        #[Max(100)]
        public array $voice = [],
        #[Min(0)]
        public ?int $lock_version = null,
    ) {}
}
