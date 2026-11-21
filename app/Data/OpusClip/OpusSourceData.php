<?php

declare(strict_types=1);

namespace App\Data\OpusClip;

final readonly class OpusSourceData
{
    public function __construct(public string $id, public ?string $title = null, public ?string $game = null) {}
}
