<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Classes\Channels\ChannelOAuth;
use App\Models\Channel;
use App\Repositories\ChannelRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

readonly class SaveChannelCredentials
{
    public function __construct(private ChannelRepository $channels) {}

    /** @param array<array-key, mixed> $credentials */
    public function run(Channel $channel, array $credentials, bool $preserveMetadata = false): void
    {
        DB::transaction(function () use ($channel, $credentials, $preserveMetadata): void {
            $current = $this->channels->findLockedOrFail($channel->id);

            if ($current->owner_id !== $channel->owner_id || ChannelOAuth::fingerprint($current) !== ChannelOAuth::fingerprint($channel)) {
                throw new RuntimeException('The channel connection changed.');
            }

            $channel->credentials = $preserveMetadata ? array_replace($current->credentials ?? [], $credentials) : $credentials;
            $this->channels->update($current, ['credentials' => $channel->credentials]);
        });
    }
}
