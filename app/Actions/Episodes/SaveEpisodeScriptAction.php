<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Data\Episodes\SaveEpisodeScriptData;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Tasks\Episodes\BuildEpisodeContext;
use App\Tasks\Episodes\SaveEpisodeScript;

readonly class SaveEpisodeScriptAction
{
    public function __construct(private BuildEpisodeContext $context, private SaveEpisodeScript $save) {}

    public function run(Episode $episode, SaveEpisodeScriptData $data): EpisodeRevision
    {
        return $this->save->run($episode, $data->script, $data->lock_version, $this->context->run($episode));
    }
}
