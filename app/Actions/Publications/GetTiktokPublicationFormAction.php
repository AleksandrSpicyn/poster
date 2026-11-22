<?php

declare(strict_types=1);

namespace App\Actions\Publications;

use App\Data\Tiktok\CreatorSettingsData;
use App\Models\Channel;
use App\Repositories\MediaRepository;
use App\Tasks\Tiktok\CreatorSettings;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetTiktokPublicationFormAction
{
    public function __construct(private MediaRepository $media, private CreatorSettings $settings) {}

    public function preview(Channel $channel, Media $media): string
    {
        $path = $this->media->publishingVideo($channel, $media->id)->getPath();
        abort_unless(is_file($path), 404);

        return $path;
    }

    /** @return array{account: string, accountId: string, inbox: bool, directUnavailable: bool, creator: CreatorSettingsData|null, videos: LengthAwarePaginator<int, Media>} */
    public function run(Channel $channel, int $page): array
    {
        $direct = $channel->canDirectPostTiktok();
        abort_unless($direct || $channel->canSendTiktokInboxVideo(), 403);

        $creator = $direct ? $this->settings->available($channel) : null;

        return [
            'account' => is_string($name = $channel->credentials['account_name'] ?? null) ? $name : $channel->name,
            'accountId' => is_string($account = $channel->credentials['channel_id'] ?? null) ? $account : '',
            'inbox' => $channel->canSendTiktokInboxVideo(),
            'directUnavailable' => $direct && ! $creator instanceof CreatorSettingsData,
            'creator' => $creator,
            'videos' => $this->media->publishingVideos($channel, $page),
        ];
    }
}
