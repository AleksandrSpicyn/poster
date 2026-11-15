<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Models\EpisodeRevision;
use App\Models\User;
use App\Tasks\Episodes\ApproveEpisodeScript;

readonly class ApproveEpisodeScriptAction
{
    public function __construct(private ApproveEpisodeScript $approve) {}

    public function run(EpisodeRevision $revision, User $actor): EpisodeRevision
    {
        return $this->approve->run($revision, $actor);
    }
}
