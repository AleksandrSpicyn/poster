<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\AssemblyManifestData;
use App\Data\Episodes\AssemblyShotData;
use App\Enums\AssemblyStatus;
use App\Enums\RevisionStatus;
use App\Enums\SeriesAudioMode;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Scene;
use App\Models\Series;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SaveEpisodeAssembly
{
    public function run(EpisodeRevision $revision, AssemblyManifestData $data): EpisodeAssembly
    {
        $revision->load('episode.series', 'scenes.shots');
        $episode = $revision->episode()->firstOrFail();
        $series = $episode->series()->firstOrFail();
        $shots = $revision->scenes->flatMap(fn(Scene $scene) => $scene->shots)->values();

        if ($shots->isEmpty() || $shots->count() !== count($data->shots) || count($data->shots) > 100) {
            throw ValidationException::withMessages(['shots' => 'Select exactly one ready take for every screenplay shot.']);
        }

        $prompts = Prompt::query()->with('media')->whereIn('id', array_map(fn(AssemblyShotData $shot): int => $shot->prompt_id, $data->shots))->get()->keyBy('id');
        $manifestShots = [];
        $selectedMedia = [];

        foreach ($data->shots as $position => $selection) {
            $shot = $shots->get($position);

            if ($shot === null) {
                throw ValidationException::withMessages(['shots' => 'Supply selected takes as an ordered list.']);
            }

            $prompt = $prompts->get($selection->prompt_id);
            $media = $prompt?->getFirstMedia(Prompt::SHOT_VIDEO);
            $end = $selection->trim_end_ms ?? $shot->duration_ms;

            if ($prompt === null || ! $prompt->generated || $prompt->shot_id !== $shot->id
                || $prompt->project_id !== $series->project_id || $media === null
                || ! in_array($media->disk, ['local', 'public'], true)
                || ! is_file($media->getPath()) || $selection->trim_start_ms < 0
                || $end > $shot->duration_ms || $end - $selection->trim_start_ms < 100
                || data_get($prompt->input_snapshot, 'context_hash') !== $revision->context_hash
                || data_get($prompt->input_snapshot, 'specification_version') !== data_get($revision->context_snapshot, 'specification_version')
                || data_get($prompt->input_snapshot, 'continuity_version') !== $revision->base_continuity_version) {
                throw ValidationException::withMessages(['shots.' . $position => 'Select a ready, current take in screenplay order and a valid trim of at least 100 ms.']);
            }

            $checksum = hash_file('sha256', $media->getPath());

            if ($checksum === false) {
                throw ValidationException::withMessages(['shots.' . $position => 'A selected take is unavailable.']);
            }

            $selectedMedia[] = $media->id;
            $manifestShots[] = [
                'shot_id' => $shot->id, 'prompt_id' => $prompt->id, 'media_id' => $media->id,
                'checksum' => $checksum, 'trim_start_ms' => $selection->trim_start_ms,
                'trim_end_ms' => $end, 'duration_ms' => $end - $selection->trim_start_ms,
                'mute_audio' => data_get($prompt->input_snapshot, 'audio_mode') === SeriesAudioMode::SILENT->value,
            ];
        }

        foreach ($data->shots as $position => $selection) {
            $prompt = $prompts->get($selection->prompt_id);
            $sourceId = data_get($prompt?->input_snapshot, 'source_media_id');
            $source = is_int($sourceId) ? Media::query()->find($sourceId) : null;

            if ($source?->model_type === new Prompt()->getMorphClass() && ! in_array($source->id, $selectedMedia, true)) {
                throw ValidationException::withMessages(['shots.' . $position => 'This take continues a different take. Select a compatible continuation or regenerate it.']);
            }
        }

        $manifest = ['schema_version' => 1, 'shots' => $manifestShots, 'duration_ms' => array_sum(array_column($manifestShots, 'duration_ms'))];
        $hash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($revision, $episode, $series, $manifest, $hash): EpisodeAssembly {
            $project = Project::query()->lockForUpdate()->findOrFail($series->project_id);

            if (! $project->is_active) {
                throw ValidationException::withMessages(['project' => 'Enable this project before building a montage.']);
            }

            $series = Series::query()->lockForUpdate()->findOrFail($series->id);
            $episode = Episode::query()->lockForUpdate()->findOrFail($episode->id);
            $revision = EpisodeRevision::query()->findOrFail($revision->id);

            $existing = $revision->assemblies()->where('manifest_hash', $hash)
                ->whereIn('status', [AssemblyStatus::QUEUED, AssemblyStatus::BUILDING, AssemblyStatus::READY, AssemblyStatus::ACCEPTED])
                ->first();

            if ($existing?->status === AssemblyStatus::ACCEPTED && $episode->accepted_assembly_id === $existing->id) {
                return $existing;
            }

            if ($series->archived_at !== null || $episode->current_revision_id !== $revision->id
                || $revision->status !== RevisionStatus::APPROVED
                || $revision->base_continuity_version !== $series->continuity_version
                || data_get($revision->context_snapshot, 'specification_version') !== $series->specification_version) {
                throw ValidationException::withMessages(['revision' => 'Assemble the current approved screenplay with an up-to-date story context.']);
            }

            if ($existing !== null) {
                return $existing;
            }

            return $revision->assemblies()->create([
                'number' => ($revision->assemblies()->orderByDesc('number')->first()->number ?? 0) + 1,
                'status' => AssemblyStatus::QUEUED, 'manifest' => $manifest, 'manifest_hash' => $hash,
                'continuity_snapshot' => [],
            ])->refresh();
        });
    }
}
