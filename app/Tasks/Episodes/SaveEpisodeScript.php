<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Classes\Story\EpisodeShotRules;
use App\Data\Episodes\EpisodeScriptData;
use App\Data\Episodes\SceneData;
use App\Data\Episodes\ShotData;
use App\Enums\SeriesOutputMode;
use App\Enums\TransitionMode;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\Series;
use App\Repositories\CharacterRevisionRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\EpisodeRevisionRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEpisodeScript
{
    public function __construct(
        private readonly EpisodeRepository $episodes,
        private readonly SeriesRepository $series,
        private readonly EpisodeRevisionRepository $episodeRevisions,
        private readonly CharacterRevisionRepository $characterRevisions,
        private readonly EpisodeShotRules $shotRules,
    ) {}

    /** @param array<string, mixed> $context */
    public function run(Episode $episode, EpisodeScriptData $script, int $expectedVersion, array $context): EpisodeRevision
    {
        return DB::transaction(function () use ($episode, $script, $expectedVersion, $context): EpisodeRevision {
            $series = $this->series->findLockedOrFail($episode->series_id);
            $episode = $this->episodes->findLockedOrFail($episode->id);

            if ($episode->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages(['lock_version' => 'The episode changed. Reload it before saving.']);
            }

            if ($series->archived_at !== null || $series->continuity_version !== ($context['continuity_version'] ?? null)
                || $series->specification_version !== ($context['specification_version'] ?? null)) {
                throw ValidationException::withMessages(['context' => 'The story context changed. Prepare a new draft.']);
            }

            $shots = collect($script->scenes)->flatMap(fn(SceneData $scene): array => $scene->shots)->values();
            $duration = $shots->sum(fn(ShotData $shot): int => $shot->duration_ms);

            if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO) {
                $this->validateCycle($series, $episode, $shots, $duration, $context);
            }

            $characters = $this->characterRevisions->approvedSelection($series, $script);

            if ($characters->count() !== count($script->character_revision_ids)) {
                throw ValidationException::withMessages(['script.character_revision_ids' => 'Select approved characters from this series.']);
            }

            $shotCount = $shots->count();

            if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO && $shotCount + $this->episodes->otherCycleShotCount($episode) > 100) {
                throw ValidationException::withMessages(['script.scenes' => 'The complete cycle supports at most 100 shots.']);
            }

            if ($shotCount > 100 || $duration > 300000) {
                throw ValidationException::withMessages(['script.scenes' => 'An episode supports at most 100 shots and five minutes.']);
            }

            $this->shotRules->validate($episode, $script, $shots, $characters, $context);

            $context['characters'] = $characters->map(fn(CharacterRevision $character): array => [
                'id' => $character->id,
                'character_id' => $character->character_id,
                'description' => $character->description,
                'appearance' => $character->appearance,
                'voice' => $character->voice,
            ])->all();

            $episode->setRelation('series', $series);
            $revision = $this->episodeRevisions->createScript($episode, $script, $context, $series->continuity_version);

            $this->episodes->update($episode, [
                'title' => $script->title,
                'current_revision_id' => $revision->id,
                'lock_version' => $expectedVersion + 1,
            ]);

            return $revision;
        });
    }

    /**
     * @param Collection<int, ShotData> $shots
     * @param array<string, mixed> $context
     */
    private function validateCycle(Series $series, Episode $episode, Collection $shots, int $duration, array $context): void
    {
        if (($series->specification['scene_mode'] ?? null) === 'same_scene') {
            $continuations = $episode->number > 1 ? $shots : $shots->skip(1);

            if ($continuations->contains(fn(ShotData $shot): bool => $shot->transition_mode === TransitionMode::NEW_SCENE)) {
                throw ValidationException::withMessages(['script.scenes' => 'This cycle keeps one scene; an additional new_scene is not allowed.']);
            }
        }

        $first = $shots->first();
        $part = data_get($context, 'cycle.part');

        if (! $first instanceof ShotData || ! is_array($part) || $first->transition_mode->value !== ($part['transition_mode'] ?? null)) {
            throw ValidationException::withMessages(['script.scenes' => 'The entry transition must match the pinned cycle boundary.']);
        }

        $seconds = $series->specification['episode_duration_seconds'] ?? null;

        if (! is_int($seconds) || $duration !== $seconds * 1000) {
            throw ValidationException::withMessages(['script.scenes' => 'The part must match the pinned cycle duration.']);
        }
    }
}
