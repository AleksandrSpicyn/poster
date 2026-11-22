<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Story\Snapshot;
use App\Data\Series\SeriesAssemblyManifestData;
use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\Series;
use App\Models\SeriesAssembly;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @extends EloquentRepository<SeriesAssembly> */
readonly class SeriesAssemblyRepository extends EloquentRepository
{
    protected function model(): string
    {
        return SeriesAssembly::class;
    }

    /** @return Collection<int, Episode> */
    public function parts(Series $series): Collection
    {
        return $series->episodes()->with('acceptedAssembly.media', 'acceptedAssembly.episodeRevision')->orderBy('number')->get();
    }

    public function isCurrent(SeriesAssembly $assembly): bool
    {
        if ($assembly->manifest_hash !== Snapshot::hash($assembly->manifest)) {
            return false;
        }

        $video = $assembly->getFirstMedia(SeriesAssembly::VIDEO);

        if (in_array($assembly->status, [AssemblyStatus::READY, AssemblyStatus::ACCEPTED], true) && ! $video instanceof Media) {
            return false;
        }

        if ($video instanceof Media && (! is_file($video->getPath()) || hash_file('sha256', $video->getPath()) !== $video->getCustomProperty('checksum'))) {
            return false;
        }

        $series = $assembly->series()->firstOrFail();
        $manifest = SeriesAssemblyManifestData::fromSnapshot($assembly->manifest);
        $parts = $this->parts($series);

        if ($parts->count() !== $series->episode_limit || count($manifest->parts) !== $parts->count()
            || $manifest->specification_version !== $series->specification_version
            || $manifest->plan_hash !== Snapshot::hash($series->cycle_plan ?? [])) {
            return false;
        }

        foreach ($parts as $index => $episode) {
            $item = $manifest->parts[$index];
            $media = $episode->acceptedAssembly?->getFirstMedia(EpisodeAssembly::VIDEO);

            if ($episode->id !== $item['episode_id'] || $episode->number !== $index + 1 || $episode->number !== $item['number']
                || $episode->validity !== EpisodeValidity::CURRENT || $episode->accepted_assembly_id !== $item['assembly_id']
                || $episode->acceptedAssembly?->status !== AssemblyStatus::ACCEPTED || $media?->id !== $item['media_id']
                || ! is_file($media->getPath()) || hash_file('sha256', $media->getPath()) !== $item['checksum']) {
                return false;
            }
        }

        return true;
    }

    public function loadSeries(SeriesAssembly $assembly): void
    {
        $assembly->loadMissing('series');
    }

    public function withContext(int $id, bool $includeParts = false): SeriesAssembly
    {
        $assembly = SeriesAssembly::query()->with('series.project', 'media')->findOrFail($id);
        if ($includeParts) {
            $assembly->load(['episodeAssemblies' => fn($query) => $query
                ->whereRelation('episodeRevision.episode', 'series_id', $assembly->series_id)
                ->select('episode_assemblies.id', 'episode_assemblies.number')]);
        }

        return $assembly;
    }

    /** @param array<string, mixed> $manifest */
    public function queue(Series $series, array $manifest, string $hash): SeriesAssembly
    {
        $existing = SeriesAssembly::query()->where('series_id', $series->id)->where('manifest_hash', $hash)
            ->whereIn('status', [AssemblyStatus::QUEUED, AssemblyStatus::BUILDING, AssemblyStatus::READY, AssemblyStatus::ACCEPTED])->first();

        if ($existing !== null) {
            return $existing;
        }

        $assembly = SeriesAssembly::query()->create([
            'series_id' => $series->id, 'number' => (SeriesAssembly::query()->where('series_id', $series->id)->orderByDesc('number')->first()->number ?? 0) + 1,
            'status' => AssemblyStatus::QUEUED, 'manifest' => $manifest, 'manifest_hash' => $hash,
        ]);

        $items = collect(SeriesAssemblyManifestData::fromSnapshot($manifest)->parts)->map(fn(array $part): array => [
            'series_assembly_id' => $assembly->id, 'episode_assembly_id' => $part['assembly_id'], 'position' => $part['number'],
        ]);
        DB::table('series_assembly_items')->insert($items->all());

        return $assembly;
    }
}
