<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Channel;
use RuntimeException;

use function Pest\Laravel\post;

/** @return array{name: string, type: string, client_id: string, client_secret: string, is_active: bool} */
function channelPayload(): array
{
    return ['name' => 'Wildlife YouTube', 'type' => 'youtube', 'client_id' => 'app-id', 'client_secret' => 'app-secret', 'is_active' => true];
}

function channelOAuthState(Channel $channel): string
{
    $response = post(route('channels.connect', $channel))->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $params);
    $state = $params['state'] ?? null;

    if (! is_string($state)) {
        throw new RuntimeException('OAuth did not return state.');
    }

    return $state;
}
