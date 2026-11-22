<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\AssemblyStatus;
use App\Models\Channel;
use App\Models\CharacterRevision;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\SeriesAssembly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @extends EloquentRepository<Media> */
readonly class MediaRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Media::class;
    }

    /** @return LengthAwarePaginator<int, Media> */
    public function publishingVideos(Channel $channel, int $page): LengthAwarePaginator
    {
        return $this->publishingQuery($channel)->latest('id')->paginate(20, page: $page);
    }

    public function publishingVideo(Channel $channel, int $id): Media
    {
        return $this->publishingQuery($channel)->findOrFail($id);
    }

    /** @return Builder<Media> */
    private function publishingQuery(Channel $channel): Builder
    {
        $project = fn(Builder $query) => $query->where('owner_id', $channel->owner_id)->where('is_active', true)
            ->whereHas('channels', fn(Builder $channels) => $channels->whereKey($channel->id));

        return Media::query()->where('mime_type', 'video/mp4')
            ->whereHasMorph('model', [Prompt::class, EpisodeAssembly::class, SeriesAssembly::class], static function (Builder $query, string $type) use ($project): void {
                match ($type) {
                    Prompt::class => $query->where('media.collection_name', 'default')->whereNull('shot_id')->where('generated', true)->whereHas('project', $project),
                    EpisodeAssembly::class => $query->where('media.collection_name', EpisodeAssembly::VIDEO)->where('status', AssemblyStatus::ACCEPTED)
                        ->whereHas('episodeRevision.episode', fn(Builder $episode) => $episode->whereColumn('accepted_assembly_id', 'episode_assemblies.id')
                            ->whereHas('series.project', $project)),
                    default => $query->where('media.collection_name', SeriesAssembly::VIDEO)->where('status', AssemblyStatus::ACCEPTED)
                        ->whereHas('series', fn(Builder $series) => $series->whereColumn('final_assembly_id', 'series_assemblies.id')->whereHas('project', $project)),
                };
            });
    }

    public function characterReference(int $revisionId, int $mediaId): Media
    {
        return Media::query()->where('model_type', new CharacterRevision()->getMorphClass())
            ->where('model_id', $revisionId)->where('collection_name', CharacterRevision::REFERENCES)
            ->findOrFail($mediaId);
    }

    public function shotVideo(int $id): Media
    {
        return Media::query()->where('collection_name', Prompt::SHOT_VIDEO)->findOrFail($id);
    }

    public function referenceForRevision(CharacterRevision $revision, int $id): Media
    {
        return $revision->media()
            ->where('collection_name', CharacterRevision::REFERENCES)
            ->findOrFail($id);
    }
}
