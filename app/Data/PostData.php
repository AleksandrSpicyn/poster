<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Channel;
use App\Models\Prompt;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Внутренний результат Publisher для SavePost; это контекст записи публикации,
 * а не входной HTTP DTO.
 */
class PostData extends Data
{
    public function __construct(
        public Channel $channel,
        public ?Prompt $prompt,
        public string $title,
        public string $description,
        public ?string $url = null,
        public ?string $externalId = null,
        public ?int $episodeAssemblyId = null,
        public ?int $publicationRequestId = null,
        public ?int $seriesAssemblyId = null,
        public ?Carbon $publishedAt = null,
    ) {}
}
