<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Data\Episodes\AssemblyManifestData;
use App\Enums\AssemblyStatus;
use App\Jobs\BuildEpisodeAssemblyJob;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Tasks\Episodes\SaveEpisodeAssembly;

readonly class RequestEpisodeAssemblyAction
{
    public function __construct(private SaveEpisodeAssembly $saveEpisodeAssembly) {}

    public function run(EpisodeRevision $revision, AssemblyManifestData $data): EpisodeAssembly
    {
        $assembly = $this->saveEpisodeAssembly->run($revision, $data);

        if ($assembly->status === AssemblyStatus::QUEUED) {
            BuildEpisodeAssemblyJob::dispatch($assembly->id)->afterCommit();
        }

        return $assembly;
    }
}
