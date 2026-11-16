<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Repositories\CharacterRevisionRepository;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Validation\ValidationException;

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
     *     schema_version: int, series_id: int, specification_version: int,
     *     specification: array<string, mixed>, continuity_version: int, episode_number: int,
     *     source_assembly_id: int|null, continuity: array<string, mixed>,
     *     characters: list<array{id: int, character_id: int, name: string, description: string, appearance: array<string, mixed>, voice: array<string, mixed>}>,
     *     history: list<array{episode_number: int, assembly_id: int|null, confirmed_events: mixed}>
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
            'characters' => array_values($this->characterRevisions->currentForContext($series->id)
                ->map(fn(CharacterRevision $revision): array => [
                    'id' => $revision->id, 'character_id' => $revision->character_id,
                    'name' => $revision->character->name ?? '',
                    'description' => $revision->description, 'appearance' => $revision->appearance, 'voice' => $revision->voice,
                ])
                ->all()),
        ];

        $history = $this->episodes->acceptedHistory($episode, $series->id);
        $snapshot['history'] = array_values($history->map(static fn(Episode $previous): array => [
            'episode_number' => $previous->number,
            'assembly_id' => $previous->accepted_assembly_id,
            'confirmed_events' => $previous->acceptedAssembly->continuity_snapshot['confirmed_events'] ?? '',
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
