<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\AssemblyStatus;
use App\Enums\UpscaleOperationStatus;
use App\Models\Channel;
use App\Models\CharacterRevision;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\Series;
use App\Models\SeriesAssembly;
use App\Models\VideoUpscaleOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @extends EloquentRepository<Media> */
readonly class MediaRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Media::class;
    }

    /** @return LazyCollection<int, Media> */
    public function publicWorkspaceMedia(): LazyCollection
    {
        $types = array_map(static fn(string $class): string => new $class()->getMorphClass(), [
            Prompt::class,
            EpisodeAssembly::class,
            SeriesAssembly::class,
            CharacterRevision::class,
        ]);

        return Media::query()->whereIn('model_type', $types)->where('disk', 'public')->lazyById();
    }

    public function seasonAsset(int $seriesId, int $id): Media
    {
        return Media::query()->where('model_type', new Series()->getMorphClass())->where('model_id', $seriesId)
            ->where('collection_name', Series::ASSETS)->where('custom_properties->kind', 'season')->findOrFail($id);
    }

    public function markPrivate(Media $media): void
    {
        $media->disk = 'local';
        $media->conversions_disk = 'local';
        $media->saveQuietly();
    }

    /** @return LengthAwarePaginator<int, Media> */
    public function publishingVideos(Channel $channel, int $page): LengthAwarePaginator
    {
        $receipts = ['publications' => fn(HasMany $query) => $query->where('channel_id', $channel->id)->where('created_by', $channel->owner_id)->with('post')->latest('id')];

        return $this->publishingQuery($channel)
            ->with(['model' => fn(MorphTo $source) => $source->morphWith([
                Prompt::class => [
                    'project.media',
                    'project.coverFrame',
                    ...$receipts,
                ],
                EpisodeAssembly::class => [
                    'episodeRevision.episode.series.project.media',
                    'episodeRevision.episode.series.project.coverFrame',
                    ...$receipts,
                ],
                SeriesAssembly::class => [
                    'series.project.media',
                    'series.project.coverFrame',
                    ...$receipts,
                ],
            ])])
            ->latest('id')->paginate(20, page: $page);
    }

    /** @return Collection<int, Media> */
    public function upscaleFiles(EpisodeAssembly|SeriesAssembly|Prompt $source, int $operationId, string $collection): Collection
    {
        return $source->media()->where('collection_name', $collection)
            ->where('custom_properties->upscale_operation_id', $operationId)
            ->orderBy('order_column')->orderBy('id')->get();
    }

    /** @return Collection<int, Media> */
    public function upscalePartResults(EpisodeAssembly|SeriesAssembly|Prompt $source, VideoUpscaleOperation $parent): Collection
    {
        return $source->media()->where('collection_name', EpisodeAssembly::UPSCALED)
            ->whereIn('id', $parent->parts()->select('result_media_id')->toBase())
            ->get()->keyBy('id');
    }

    public function episodeExport(EpisodeAssembly|SeriesAssembly|Prompt $assembly, int $id, ?string $checksum = null): ?Media
    {
        $media = $assembly->media()->whereKey($id)->where('disk', 'local')
            ->whereIn('collection_name', [$assembly instanceof Prompt ? 'default' : $assembly::VIDEO, EpisodeAssembly::UPSCALED])->first();

        if ($media === null || ! is_file($media->getPath())) {
            return null;
        }

        $actual = hash_file('sha256', $media->getPath());

        if ($actual === false || ($checksum !== null && $checksum !== $actual)) {
            return null;
        }

        if ($media->collection_name === EpisodeAssembly::UPSCALED && ! $this->hasReadyUpscale($assembly, $id, $actual)) {
            return null;
        }

        return $media;
    }

    public function hasReadyUpscale(EpisodeAssembly|SeriesAssembly|Prompt $source, int $mediaId, ?string $checksum = null): bool
    {
        return $source->upscales()->whereNull('parent_id')->where('result_media_id', $mediaId)->where('status', UpscaleOperationStatus::READY)
            ->when($checksum !== null, fn(Builder $query) => $query->where('result_sha256', $checksum))->exists();
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
            ->whereHasMorph('model', [
                Prompt::class,
                EpisodeAssembly::class,
                SeriesAssembly::class,
            ], static function (Builder $query, string $type) use ($project): void {
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

    /**
     * @param array<int, int> $ids
     * @return Collection<int, Media>
     */
    public function byIds(array $ids): Collection
    {
        return Media::query()->whereKey($ids)->get()->keyBy('id');
    }

    public function retainPreviousVoiceSample(Media $media): void
    {
        $media->collection_name = CharacterRevision::PREVIOUS_VOICE_SAMPLES;
        $media->save();
    }

    public function referenceByFileName(CharacterRevision $revision, string $fileName): ?Media
    {
        return $revision->media()
            ->whereIn('collection_name', [CharacterRevision::REFERENCES, CharacterRevision::VOICE_SAMPLE])
            ->where('file_name', $fileName)
            ->first();
    }

    public function referenceForRevision(CharacterRevision $revision, int $id): Media
    {
        return $revision->media()
            ->whereIn('collection_name', [CharacterRevision::REFERENCES, CharacterRevision::VOICE_SAMPLE])
            ->findOrFail($id);
    }
}
