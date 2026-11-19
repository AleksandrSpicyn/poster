<?php

declare(strict_types=1);

namespace App\Data;

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
    ) {}
}
