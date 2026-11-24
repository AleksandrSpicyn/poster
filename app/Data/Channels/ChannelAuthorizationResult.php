<?php

declare(strict_types=1);

namespace App\Data\Channels;

use App\Models\Channel;

final readonly class ChannelAuthorizationResult
{
    public function __construct(public ?Channel $channel, public ?string $error = null) {}
}
