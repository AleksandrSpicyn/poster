<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\DialogueLineData;
use App\Data\Episodes\EpisodeScriptData;
use App\Data\Episodes\SceneData;
use App\Data\Episodes\ShotData;
use App\Enums\RevisionStatus;
use App\Models\Channel;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use App\Models\Scene;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GetEpisode
{
    /** @return array{episode: Episode, characters: Collection<int, CharacterRevision>, assemblies: Collection<int, EpisodeAssembly>, publications: Collection<int, PublicationRequest>, channels: Collection<int, Channel>, script: EpisodeScriptData|null} */
    public function run(Episode $episode): array
    {
        $episode->load([
            'series.project',
            'currentRevision.characterRevisions.character',
            'currentRevision.scenes.shots.prompts' => static function (HasMany $query): void {
                $query->with('media', 'generationOperations')->orderByDesc('take_number')->limit(10);
            },
            'acceptedAssembly.media',
            'predecessor.acceptedAssembly.media',
        ]);
        $series = $episode->series()->firstOrFail();
        $assemblies = EpisodeAssembly::query()
            ->whereHas('episodeRevision', fn($query) => $query->where('episode_id', $episode->id))
            ->with('media')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $characters = CharacterRevision::query()
            ->where('status', RevisionStatus::APPROVED)
            ->whereHas('character', fn($query) => $query->where('series_id', $series->id)->whereNull('archived_at'))
            ->with('character')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $channels = Channel::query()->where('project_id', $series->project_id)->orderBy('name')->limit(100)->get();
        $publications = PublicationRequest::query()->whereIn('episode_assembly_id', $assemblies->modelKeys())->with('channel')->orderByDesc('id')->limit(200)->get();
        $revision = $episode->currentRevision;
        $script = null;

        if ($revision !== null) {
            $script = new EpisodeScriptData(
                $revision->title,
                $revision->objective,
                array_values($revision->characterRevisions->map(fn(CharacterRevision $character): int => $character->id)->all()),
                array_values($revision->scenes->map(fn(Scene $scene): SceneData => new SceneData(
                    $scene->title,
                    is_string($scene->setting['location'] ?? null) ? $scene->setting['location'] : '',
                    is_string($scene->setting['time'] ?? null) ? $scene->setting['time'] : '',
                    $scene->objective,
                    is_string($scene->initial_state['description'] ?? null) ? $scene->initial_state['description'] : '',
                    is_string($scene->expected_final_state['description'] ?? null) ? $scene->expected_final_state['description'] : '',
                    $scene->is_closed,
                    array_values($scene->shots->map(fn(Shot $shot): ShotData => new ShotData(
                        $shot->duration_ms,
                        $shot->action,
                        is_string($shot->camera['description'] ?? null) ? $shot->camera['description'] : '',
                        $shot->transition_mode,
                        array_values(collect($shot->dialogue)->map(fn(array $line): DialogueLineData => DialogueLineData::from($line))->all()),
                        $shot->source_prompt_id,
                        $shot->source_assembly_id,
                        is_int($shot->source_anchor['time_ms'] ?? null) ? $shot->source_anchor['time_ms'] : 0,
                    ))->all()),
                ))->all()),
            );
        }

        return ['episode' => $episode, 'characters' => $characters, 'assemblies' => $assemblies, 'publications' => $publications, 'channels' => $channels, 'script' => $script];
    }
}
