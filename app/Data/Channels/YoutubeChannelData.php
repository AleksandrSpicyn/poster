<?php

declare(strict_types=1);

namespace App\Data\Channels;

final readonly class YoutubeChannelData
{
    public function __construct(public string $id, public string $name) {}
}
