<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use App\Models\Channel;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

class EpisodeWorkspaceData extends Data
{
    /**
     * @param Collection<int, CharacterRevision> $characters
     * @param Collection<int, EpisodeAssembly> $assemblies
     * @param Collection<int, PublicationRequest> $publications
     * @param Collection<int, Channel> $channels
     */
    public function __construct(
        public Episode $episode,
        public Collection $characters,
        public Collection $assemblies,
        public Collection $publications,
        public Collection $channels,
        public ?EpisodeScriptData $script = null,
    ) {}
}
