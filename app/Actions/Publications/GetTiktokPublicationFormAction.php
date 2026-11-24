<?php

declare(strict_types=1);

namespace App\Actions\Publications;

use App\Data\Tiktok\CreatorSettingsData;
use App\Models\Channel;
use App\Repositories\MediaRepository;
use App\Tasks\Tiktok\CreatorSettings;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetTiktokPublicationFormAction
{
    public function __construct(private MediaRepository $media, private CreatorSettings $settings) {}

    /** @return array{account: string, accountId: string, inbox: bool, directUnavailable: Closure(): bool, creator: Closure(): ?CreatorSettingsData, videos: Closure(): LengthAwarePaginator<int, Media>} */
    public function run(Channel $channel, int $page): array
    {
        $direct = $channel->canDirectPostTiktok();
        abort_unless($direct || $channel->canSendTiktokInboxVideo(), 403);

        $creator = fn(): ?CreatorSettingsData => $direct ? once(fn(): ?CreatorSettingsData => $this->settings->available($channel)) : null;

        return [
            'account' => is_string($name = $channel->credentials['account_name'] ?? null) ? $name : $channel->name,
            'accountId' => is_string($account = $channel->credentials['channel_id'] ?? null) ? $account : '',
            'inbox' => $channel->canSendTiktokInboxVideo(),
            'directUnavailable' => fn(): bool => $direct && ! $creator() instanceof CreatorSettingsData,
            'creator' => $creator,
            'videos' => fn(): LengthAwarePaginator => $this->media->publishingVideos($channel, $page),
        ];
    }
}
