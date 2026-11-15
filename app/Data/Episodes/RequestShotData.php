<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class RequestShotData extends Data
{
    public function __construct(
        #[Min(1)] public ?int $source_prompt_id = null,
        #[Max(100)] public ?string $request_key = null,
        public bool $new_take = false,
    ) {}
}
