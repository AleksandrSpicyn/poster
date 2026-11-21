<?php

declare(strict_types=1);

namespace App\Data\OpusClip;

final readonly class GamingTrendReport
{
    /** @param list<array{title: string, channel: string, views: string, views_per_day?: int|null, url: string}> $entries Title, channel, public views and video URL. */
    public function __construct(public string $game, public array $entries, public ?string $checkedAt = null) {}
}
