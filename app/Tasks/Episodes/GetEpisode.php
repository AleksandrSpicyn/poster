<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\DialogueLineData;
use App\Data\Episodes\EpisodeScriptData;
use App\Data\Episodes\EpisodeWorkspaceData;
use App\Data\Episodes\SceneData;
use App\Data\Episodes\ShotData;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\Scene;
use App\Models\Shot;
use App\Repositories\EpisodeRepository;

class GetEpisode
{
    public function __construct(private readonly EpisodeRepository $episodes) {}

    public function run(Episode $episode): EpisodeWorkspaceData
    {
        $workspace = $this->episodes->workspace($episode);
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
                        $shot->title,
                        $shot->plan_type,
                    ))->all()),
                ))->all()),
                $revision->public_caption,
            );
        }

        $workspace->script = $script;

        return $workspace;
    }
}
