<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\RevisionStatus;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\Series;
use Illuminate\Validation\ValidationException;

class BuildEpisodeContext
{
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
        $series = Series::query()->findOrFail($episode->series_id);

        if ($series->archived_at !== null) {
            throw ValidationException::withMessages(['series' => 'This series is archived.']);
        }

        $source = null;

        if ($episode->predecessor_episode_id !== null) {
            $previous = Episode::query()->where('series_id', $series->id)->findOrFail($episode->predecessor_episode_id);

            if ($previous->validity !== EpisodeValidity::CURRENT || $previous->accepted_assembly_id === null) {
                throw ValidationException::withMessages(['predecessor' => 'Accept the preceding episode before preparing its continuation.']);
            }

            $source = EpisodeAssembly::query()->findOrFail($previous->accepted_assembly_id);

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
            'characters' => array_values(CharacterRevision::query()
                ->with('character')
                ->where('status', RevisionStatus::APPROVED)
                ->whereHas('character', fn($query) => $query->where('series_id', $series->id)
                    ->whereNull('archived_at')
                    ->whereColumn('characters.current_revision_id', 'character_revisions.id'))
                ->orderBy('id')
                ->limit(10)
                ->get()
                ->map(fn(CharacterRevision $revision): array => [
                    'id' => $revision->id, 'character_id' => $revision->character_id,
                    'name' => $revision->character->name ?? '',
                    'description' => $revision->description, 'appearance' => $revision->appearance, 'voice' => $revision->voice,
                ])
                ->all()),
        ];

        $history = Episode::query()->where('series_id', $series->id)
            ->where('number', '<', $episode->number)
            ->where('validity', EpisodeValidity::CURRENT)
            ->whereHas('acceptedAssembly', fn($query) => $query->where('status', AssemblyStatus::ACCEPTED))
            ->with('acceptedAssembly')
            ->orderByDesc('number')->limit(50)->get()->reverse()->values();
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
