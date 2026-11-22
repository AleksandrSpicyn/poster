<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Classes\Story\Snapshot;
use App\Data\Series\CyclePlanData;
use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\SeriesOutputMode;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Repositories\CharacterRevisionRepository;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BuildEpisodeContext
{
    public function __construct(
        private readonly EpisodeAssemblyRepository $assemblies,
        private readonly SeriesRepository $series,
        private readonly EpisodeRepository $episodes,
        private readonly CharacterRevisionRepository $characterRevisions,
    ) {}

    /**
     * @return array{
     *     cycle?: array<string, mixed>, lead_character_id?: int, series_voice?: string,
     *     schema_version: int, series_id: int, specification_version: int,
     *     specification: array<string, mixed>, continuity_version: int, episode_number: int,
     *     source_assembly_id: int|null, continuity: array<string, mixed>,
     *     characters: list<array{id: int, character_id: int, name: string, description: string, appearance: array<string, mixed>, voice: array<string, mixed>}>,
     *     history: list<array{episode_number: int, assembly_id: int|null, confirmed_events: mixed, previous_screenplay: array{title: string, objective: string}}>
     * }
     */
    public function run(Episode $episode): array
    {
        $series = $this->series->findOrFail($episode->series_id);

        if ($series->archived_at !== null) {
            throw ValidationException::withMessages(['series' => 'This series is archived.']);
        }

        $source = null;

        if ($episode->predecessor_episode_id !== null) {
            $previous = $this->episodes->forSeries($series->id, $episode->predecessor_episode_id);

            if ($previous->validity !== EpisodeValidity::CURRENT || $previous->accepted_assembly_id === null) {
                throw ValidationException::withMessages(['predecessor' => 'Accept the preceding episode before preparing its continuation.']);
            }

            $source = $this->assemblies->findOrFail($previous->accepted_assembly_id);

            if ($source->status !== AssemblyStatus::ACCEPTED) {
                throw ValidationException::withMessages(['predecessor' => 'The preceding assembly has not been accepted.']);
            }
        }

        $snapshot = [
            'schema_version' => 1,
            'series_id' => $series->id,
            'specification_version' => $series->specification_version,
            'specification' => $series->specification,
            'continuity_version' => $series->continuity_version,
            'episode_number' => $episode->number,
            'source_assembly_id' => $source?->id,
            'continuity' => $source->continuity_snapshot ?? [],
            'characters' => array_values($this->characterRevisions->currentForContext($series->id, $series->cast_settings?->lead_character_id)
                ->map(fn(CharacterRevision $revision): array => [
                    'id' => $revision->id, 'character_id' => $revision->character_id,
                    'name' => $revision->character->name ?? '',
                    'description' => $revision->description, 'appearance' => $revision->appearance, 'voice' => $revision->voice,
                ])
                ->all()),
        ];

        $cast = $series->cast_settings;

        if ($cast !== null) {
            if ($cast->lead_character_id !== null && in_array($cast->lead_character_id, array_column($snapshot['characters'], 'character_id'), true)) {
                $snapshot['lead_character_id'] = $cast->lead_character_id;
            }

            if ($cast->use_series_voice && filled($cast->voice_description) && ($cast->lead_character_id === null || isset($snapshot['lead_character_id']))) {
                $snapshot['series_voice'] = $cast->voice_description;
            }
        }

        if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO) {
            $plan = CyclePlanData::validateAndCreate($series->cycle_plan ?? []);
            $part = $plan->parts[$episode->number - 1] ?? throw new RuntimeException('The part has no place in the cycle.');
            $snapshot['cycle'] = ['objective' => $plan->objective, 'structure' => $plan->structure->value,
                'part' => $part->toArray(), 'part_count' => count($plan->parts), 'plan_hash' => Snapshot::hash($series->cycle_plan ?? [])];
        }

        $history = $this->episodes->acceptedHistory($episode, $series->id);
        $snapshot['history'] = array_values($history->map(static fn(Episode $previous): array => [
            'episode_number' => $previous->number,
            'assembly_id' => $previous->accepted_assembly_id,
            'confirmed_events' => $previous->acceptedAssembly->continuity_snapshot['confirmed_events'] ?? '',
            'previous_screenplay' => [
                'title' => $previous->acceptedAssembly->episodeRevision->title ?? '',
                'objective' => $previous->acceptedAssembly->episodeRevision->objective ?? '',
            ],
        ])->all());

        while (strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)) > 64000 && $snapshot['history'] !== []) {
            array_shift($snapshot['history']);
        }

        if (strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)) > 64000) {
            throw ValidationException::withMessages(['context' => 'The required story context exceeds 64 KB. Shorten the series specification or confirmed summary.']);
        }

        return $snapshot;
    }
}
