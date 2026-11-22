<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Classes\Story\Snapshot;
use App\Data\Series\SeriesAssemblyManifestData;
use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\SeriesOutputMode;
use App\Models\EpisodeAssembly;
use App\Models\Series;
use App\Models\SeriesAssembly;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesAssemblyRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class SaveSeriesAssembly
{
    public function __construct(private ProjectRepository $projects, private SeriesRepository $series, private SeriesAssemblyRepository $assemblies) {}

    public function run(Series $series): SeriesAssembly
    {
        return DB::transaction(function () use ($series): SeriesAssembly {
            $project = $this->projects->findLockedOrFail($series->project_id);
            $series = $this->series->findLockedOrFail($series->id);
            $parts = $this->assemblies->parts($series);

            if (! $project->is_active || $series->archived_at !== null || $series->cycle_paused_at !== null || $series->output_mode !== SeriesOutputMode::SINGLE_VIDEO || $series->cycle_plan === null
                || $series->release_started_at !== null || $parts->count() !== $series->episode_limit) {
                throw ValidationException::withMessages(['assembly' => 'Для сборки нужен активный цикл со всеми эпизодами до начала выпуска.']);
            }

            $manifest = [];

            foreach ($parts as $index => $episode) {
                $assembly = $episode->acceptedAssembly;
                $media = $assembly?->getFirstMedia(EpisodeAssembly::VIDEO);

                if ($episode->number !== $index + 1 || $episode->validity !== EpisodeValidity::CURRENT || $assembly?->status !== AssemblyStatus::ACCEPTED
                    || ! $media instanceof Media || $assembly->duration_ms === null || ! is_file($media->getPath()) || hash_file('sha256', $media->getPath()) !== $media->getCustomProperty('checksum')) {
                    throw ValidationException::withMessages(['assembly' => 'Проверьте, что у каждого эпизода есть готовая принятая версия и её видео доступно.']);
                }

                $manifest[] = ['number' => $episode->number, 'episode_id' => $episode->id, 'assembly_id' => $assembly->id,
                    'media_id' => $media->id, 'checksum' => hash_file('sha256', $media->getPath()) ?: throw new RuntimeException('Missing accepted source'), 'duration_ms' => $assembly->duration_ms < 100 ? $assembly->duration_ms : (int) round(intdiv($assembly->duration_ms * 24, 1000) * 1000 / 24)];
            }

            $data = new SeriesAssemblyManifestData($manifest, $series->specification_version, Snapshot::hash($series->cycle_plan ?? []));
            /** @var array<string, mixed> $snapshot */
            $snapshot = $data->toArray();

            return $this->assemblies->queue($series, $snapshot, Snapshot::hash($snapshot));
        });
    }
}
