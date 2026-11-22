<?php

declare(strict_types=1);

namespace App\Data;

final readonly class MetricTargetData
{
    /** @param list<MetricOriginData> $origins */
    public function __construct(public string $mediaId, public string $title, public array $origins) {}
}
