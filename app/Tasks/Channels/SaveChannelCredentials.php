<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Classes\Story\Snapshot;
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

            if ($current->client_id !== $channel->client_id || $current->client_secret !== $channel->client_secret || Snapshot::hash(['credentials' => $current->credentials]) !== Snapshot::hash(['credentials' => $channel->credentials])) {
                throw new RuntimeException('The channel connection changed.');
            }

            $channel->credentials = $preserveMetadata ? array_replace($current->credentials ?? [], $credentials) : $credentials;
            $this->channels->update($current, ['credentials' => $channel->credentials]);
        });
    }
}
