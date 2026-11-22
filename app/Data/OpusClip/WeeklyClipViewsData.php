<?php

declare(strict_types=1);

namespace App\Data\OpusClip;

use Spatie\LaravelData\Data;

final class WeeklyClipViewsData extends Data
{
    /** @param list<array{title: string, views: int|float}> $clips */
    public function __construct(public string $from, public string $to, public ?string $observedOn, public array $clips) {}
}
