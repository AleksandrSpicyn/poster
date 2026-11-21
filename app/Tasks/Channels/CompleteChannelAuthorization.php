<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Classes\Channels\ChannelOAuth;
use App\Data\Channels\OAuthCallbackData;
use App\Enums\ChannelType;
use App\Exceptions\ChannelAuthorizationException;
use App\Models\Channel;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Support\Integrations\Payload;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use RuntimeException;

readonly class CompleteChannelAuthorization
{
    public function __construct(private ChannelOAuth $oauth, private ChannelRepository $channels, private Session $session) {}

    public function pendingChannel(ChannelType $provider, User $actor, OAuthCallbackData $data): ?Channel
    {
        $pending = $this->session->get('channel_oauth.' . hash('sha256', $data->state));

        if (! is_array($pending) || ($pending['provider'] ?? null) !== $provider->value || ($pending['user_id'] ?? null) !== $actor->id || ! is_int($pending['channel_id'] ?? null)) {
            return null;
        }

        $channel = $this->channels->find($pending['channel_id']);

        return $channel?->type === $provider ? $channel : null;
    }

    public function run(Channel $channel, User $actor, OAuthCallbackData $data, ?ChannelType $provider): void
    {
        $pending = $this->session->pull('channel_oauth.' . hash('sha256', $data->state));
        $channel = $this->channels->findOrFail($channel->id);

        if (! is_array($pending) || ($pending['channel_id'] ?? null) !== $channel->id || ($pending['user_id'] ?? null) !== $actor->id
            || ! is_int($pending['expires_at'] ?? null) || $pending['expires_at'] < now()->timestamp
            || ($pending['provider'] ?? null) !== $provider?->value
            || ($pending['fingerprint'] ?? null) !== $this->oauth->fingerprint($channel)
            || $data->error !== null || $data->code === null) {
            throw new RuntimeException('Invalid or cancelled authorization.');
        }

        $fingerprint = $this->oauth->fingerprint($channel);
        $credentials = $this->oauth->exchange($channel, $data->code, $provider instanceof ChannelType ? route('channels.provider-callback', $provider) : route('channels.callback', $channel));
        $identity = $channel->type === ChannelType::INSTAGRAM ? $credentials['account_name'] : $credentials['channel_id'];

        if ($channel->external_account !== null && $identity !== $channel->external_account) {
            throw new RuntimeException('Select the account assigned to this channel.');
        }

        DB::transaction(function () use ($channel, $fingerprint, $credentials): void {
            $current = $this->channels->findLockedOrFail($channel->id);

            if ($fingerprint !== $this->oauth->fingerprint($current)) {
                throw new RuntimeException('Application settings changed during authorization.');
            }

            if ($current->type === ChannelType::TIKTOK && $this->channels->belongsToClippingWorkspace($current)) {
                if (! $this->channels->isDedicatedToOneProject($current)) {
                    throw new ChannelAuthorizationException('Attach a dedicated TikTok account to the clipping project.');
                }

                $current->external_account = Payload::string($credentials['channel_id'] ?? null, 'channel_id');
            }

            $this->channels->update($current, ['credentials' => $credentials]);
        });
    }
}
