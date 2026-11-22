<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Data\Series\AcceptSeriesVideoData;
use App\Data\Series\SeriesAssemblyManifestData;
use App\Enums\AssemblyStatus;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesAssemblyRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class AcceptSeriesAssembly
{
    public function __construct(private ProjectRepository $projects, private SeriesRepository $series, private SeriesAssemblyRepository $assemblies) {}

    public function run(SeriesAssembly $assembly, AcceptSeriesVideoData $data, User $actor): SeriesAssembly
    {
        return DB::transaction(function () use ($assembly, $data, $actor): SeriesAssembly {
            $assembly = $this->assemblies->withContext($assembly->id);
            $project = $this->projects->findLockedOrFail($assembly->series->project_id);
            $series = $this->series->findLockedOrFail($assembly->series_id);
            $assembly = $this->assemblies->findLockedOrFail($assembly->id);
            $media = $assembly->getFirstMedia(SeriesAssembly::VIDEO);

            if (! $project->is_active || $series->archived_at !== null || $series->cycle_paused_at !== null || $series->release_started_at !== null
                || $assembly->status !== AssemblyStatus::READY || $assembly->lock_version !== $data->lock_version || ! $data->boundaries_reviewed
                || ! $this->assemblies->isCurrent($assembly) || ! $media instanceof Media || ! is_file($media->getPath())
                || hash_file('sha256', $media->getPath()) !== $media->getCustomProperty('checksum')) {
                throw ValidationException::withMessages(['assembly' => 'Review the current complete video and every boundary before accepting. A claimed release cannot change its file.']);
            }

            $parts = SeriesAssemblyManifestData::fromSnapshot($assembly->manifest)->parts;

            foreach (array_slice($parts, 0, -1) as $part) {
                if (data_get($assembly->review, 'boundaries.after_' . $part['number'] . '.reviewed') !== true) {
                    throw ValidationException::withMessages(['boundaries_reviewed' => 'Сохраните проверку каждого стыка перед приёмкой сборки.']);
                }
            }

            $this->assemblies->update($assembly, ['status' => AssemblyStatus::ACCEPTED, 'accepted_at' => now(), 'accepted_by' => $actor->id,
                'lock_version' => $assembly->lock_version + 1, 'review' => [...($assembly->review ?? []), 'boundaries_reviewed' => true, 'notes' => $data->review_notes,
                    'manifest_hash' => $assembly->manifest_hash, 'video_checksum' => $media->getCustomProperty('checksum')]]);
            $this->series->update($series, ['final_assembly_id' => $assembly->id]);

            return $assembly;
        });
    }
}
