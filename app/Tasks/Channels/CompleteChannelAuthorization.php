<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Classes\Channels\ChannelOAuth;
use App\Classes\Channels\FacebookOAuth;
use App\Data\Channels\OAuthCallbackData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Support\Integrations\Payload;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use RuntimeException;

readonly class CompleteChannelAuthorization
{
    public function __construct(private ChannelOAuth $oauth, private FacebookOAuth $facebook, private ChannelRepository $channels, private Session $session) {}

    public function pendingChannel(ChannelType $provider, User $actor, OAuthCallbackData $data): ?Channel
    {
        $pending = $this->session->get('channel_oauth.' . hash('sha256', $data->state));

        if (! is_array($pending) || ($pending['provider'] ?? null) !== $provider->value || ($pending['user_id'] ?? null) !== $actor->id || ! is_int($pending['channel_id'] ?? null)) {
            return null;
        }

        $channel = $this->channels->find($pending['channel_id']);

        return $channel?->owner_id === $actor->id && $channel->type === $provider ? $channel : null;
    }

    public function run(Channel $channel, User $actor, OAuthCallbackData $data, ?ChannelType $provider): void
    {
        $pending = $this->session->pull('channel_oauth.' . hash('sha256', $data->state));
        $channel = $this->channels->findOrFail($channel->id);

        $fingerprint = ChannelOAuth::fingerprint($channel);

        if ($channel->owner_id !== $actor->id || ! is_array($pending) || ($pending['channel_id'] ?? null) !== $channel->id || ($pending['user_id'] ?? null) !== $actor->id
            || ! is_int($pending['expires_at'] ?? null) || $pending['expires_at'] < now()->timestamp
            || ($pending['provider'] ?? null) !== $provider?->value
            || ($pending['fingerprint'] ?? null) !== $fingerprint
            || $data->error !== null || $data->code === null) {
            throw new RuntimeException('Invalid or cancelled authorization.');
        }

        if ($channel->type === ChannelType::FACEBOOK) {
            $this->facebook->discover($channel, $data->code, ChannelOAuth::redirectUri($channel->type));

            return;
        }

        $credentials = $this->oauth->exchange($channel, $data->code, $provider instanceof ChannelType ? ChannelOAuth::redirectUri($provider) : route('channels.callback', $channel));
        $identity = $channel->type === ChannelType::INSTAGRAM ? $credentials['account_name'] : $credentials['channel_id'];

        if ($channel->external_account !== null && $identity !== $channel->external_account) {
            throw new RuntimeException('Select the account assigned to this channel.');
        }

        DB::transaction(function () use ($channel, $actor, $fingerprint, $credentials): void {
            $current = $this->channels->findLockedOrFail($channel->id);

            if ($current->owner_id !== $actor->id || $fingerprint !== ChannelOAuth::fingerprint($current)) {
                throw new RuntimeException('Application settings changed during authorization.');
            }

            if ($current->type === ChannelType::TIKTOK && $this->channels->belongsToClippingWorkspace($current)) {
                $current->external_account = Payload::string($credentials['channel_id'] ?? null, 'channel_id');
            }

            $this->channels->update($current, ['credentials' => $credentials]);
        });
    }
}
