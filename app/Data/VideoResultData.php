<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/** Звук может находиться в том же файле, что и видео, либо поступить отдельно. */
class VideoResultData extends Data
{
    public function __construct(public string $videoUrl, public ?string $audioUrl = null) {}
}
