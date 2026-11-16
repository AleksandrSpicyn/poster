<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Classes\Channels\ChannelOAuth;
use App\Models\Channel;
use App\Models\User;
use App\Repositories\ChannelRepository;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

readonly class StartChannelAuthorization
{
    public function __construct(private ChannelOAuth $oauth, private ChannelRepository $channels, private Session $session) {}

    public function run(Channel $channel, User $actor): string
    {
        $channel = $this->channels->findOrFail($channel->id);
        $state = Str::random(64);
        $url = $this->oauth->authorizationUrl($channel, $state);
        $this->session->put('channel_oauth.' . hash('sha256', $state), [
            'channel_id' => $channel->id, 'user_id' => $actor->id,
            'fingerprint' => $this->oauth->fingerprint($channel), 'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return $url;
    }
}
