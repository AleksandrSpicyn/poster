<?php

declare(strict_types=1);

namespace App\Actions\Publications;

use App\Data\Tiktok\CreatorSettingsData;
use App\Models\Channel;
use App\Repositories\MediaRepository;
use App\Tasks\Tiktok\CreatorSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;
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

    /** @return array{account: string, accountId: string, inbox: bool, creator: CreatorSettingsData|null, videos: LengthAwarePaginator<int, array{id: int, label: string, url: string}>} */
    public function run(Channel $channel, int $page): array
    {
        abort_unless($channel->canDirectPostTiktok() || $channel->canSendTiktokInboxVideo(), 403);

        try {
            $creator = $channel->canDirectPostTiktok() ? $this->settings->run($channel) : null;
        } catch (RuntimeException|ConnectionException) {
            $creator = null;
        }

        return [
            'account' => is_string($name = $channel->credentials['account_name'] ?? null) ? $name : $channel->name,
            'accountId' => is_string($account = $channel->credentials['channel_id'] ?? null) ? $account : '',
            'inbox' => $channel->canSendTiktokInboxVideo(),
            'creator' => $creator,
            'videos' => $this->media->publishingVideos($channel, $page)->through(static fn(Media $media): array => [
                'id' => $media->id, 'label' => $media->name . ' (#' . $media->id . ')',
                'url' => route('posts.tiktok.preview', ['channel' => $channel, 'media' => $media]),
            ]),
        ];
    }
}
