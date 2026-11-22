<?php

declare(strict_types=1);

namespace App\Data;

use App\Data\Tiktok\TiktokPublicationData;
use App\Models\Prompt;

final readonly class PublishVideoData
{
    public function __construct(
        public string $path,
        public string $url,
        public string $title,
        public string $description,
        public ?int $episodeAssemblyId,
        public int $publicationRequestId,
        public bool $madeForKids = false,
        public ?int $seriesAssemblyId = null,
        public bool $usePublicDescription = false,
        public ?Prompt $short = null,
        public ?TiktokPublicationData $tiktok = null,
    ) {}
}
