<?php

declare(strict_types=1);

namespace App\Actions\Publications;

use App\Classes\Story\Snapshot;
use App\Data\Episodes\PublishEpisodeData;
use App\Data\Tiktok\CreatorSettingsData;
use App\Data\Tiktok\TiktokPublicationData;
use App\Enums\TiktokDeliveryMode;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PublicationRequestRepository;
use App\Tasks\Publications\CreatePublicationRequest;
use App\Tasks\Publications\CreateSeriesPublication;
use App\Tasks\Tiktok\CreatorSettings;
use App\Tasks\Tiktok\ValidateDirectPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class RequestTiktokPublicationAction
{
    public function __construct(
        private ChannelRepository $channels,
        private MediaRepository $media,
        private PublicationRequestRepository $publications,
        private CreatePublicationRequest $episode,
        private CreateSeriesPublication $series,
        private CreatorSettings $settings,
        private ValidateDirectPost $validate,
    ) {}

    public function run(Channel $channel, User $owner, TiktokPublicationData $data): PublicationRequest
    {
        $this->authorize($channel, $owner, $data);
        $media = $this->media->publishingVideo($channel, $data->media_id);

        if ($data->mode === TiktokDeliveryMode::DIRECT) {
            $settings = $this->settings->available($channel);

            if (! $settings instanceof CreatorSettingsData) {
                throw ValidationException::withMessages(['mode' => 'Не удалось получить настройки прямой публикации. Повторите проверку аккаунта.']);
            }

            $this->validate->run($data, $settings, $media->getPath());
        }

        return DB::transaction(function () use ($channel, $data, $owner): PublicationRequest {
            $channel = $this->channels->findLockedOrFail($channel->id);
            $this->authorize($channel, $owner, $data);
            $source = $this->media->publishingVideo($channel, $data->media_id)->model;
            $selection = new PublishEpisodeData([$channel->id]);
            $request = match (true) {
                $source instanceof EpisodeAssembly => $this->episode->run($source, $selection, $owner, tiktok: $data)->sole(),
                $source instanceof SeriesAssembly => $this->series->run($source, $selection, $owner, tiktok: $data)->sole(),
                $source instanceof Prompt => $this->publications->forShort($source, $channel, $owner->id, $data),
                default => abort(404),
            };

            if (! $request->wasRecentlyCreated && Snapshot::hash($request->payload['tiktok'] ?? []) !== Snapshot::hash($data->toArray())) {
                throw ValidationException::withMessages(['mode' => 'This video already has a request with different settings. Check its publication status.']);
            }

            if ($request->wasRecentlyCreated && ! $request->scheduled_at?->isFuture()) {
                PublishEpisodeJob::dispatch($request->id)->afterCommit();
            }

            return $request;
        }, attempts: 3);
    }

    private function authorize(Channel $channel, User $owner, TiktokPublicationData $data): void
    {
        abort_unless($channel->owner_id === $owner->id && $owner->hasVerifiedEmail(), 404);

        if ($data->account_id !== ($channel->credentials['channel_id'] ?? null)
            || ! ($data->mode === TiktokDeliveryMode::DIRECT ? $channel->canDirectPostTiktok() : $channel->canSendTiktokInboxVideo())) {
            throw ValidationException::withMessages(['mode' => 'The destination or its permissions changed. Reopen the publication form.']);
        }
    }
}
