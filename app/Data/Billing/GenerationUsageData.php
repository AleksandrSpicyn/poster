<?php

declare(strict_types=1);

namespace App\Data\Billing;

use Illuminate\Support\Collection;
use stdClass;

final readonly class GenerationUsageData
{
    /**
     * @param Collection<int, stdClass> $sources
     * @param list<array{shot_id: int, prompt_id: ?int, seconds: int, status: string}>|null $shots
     */
    public function __construct(
        public bool $enforced,
        public ?int $estimate,
        public int $available,
        public Collection $sources,
        public ?array $shots = null,
    ) {}
}
