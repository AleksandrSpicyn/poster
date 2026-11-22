<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\OpusClip;
use App\Models\Post;

final readonly class MetricOriginData
{
    /** @param class-string<Post|OpusClip> $type */
    public function __construct(public string $type, public int $id, public int $projectId) {}
}
