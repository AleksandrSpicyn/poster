<?php

declare(strict_types=1);

namespace App\Data\Channels;

use App\Models\Channel;

final readonly class ChannelFormData
{
    /** @param list<string> $requestedScopes */
    public function __construct(public Channel $channel, public array $requestedScopes, public ?string $callbackUrl) {}
}
