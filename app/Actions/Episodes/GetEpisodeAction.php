<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Classes\Publishers\Facebook\FacebookReelFile;
use App\Data\Episodes\EpisodeWorkspaceData;
use App\Enums\ChannelType;
use App\Models\Episode;
use App\Repositories\VideoUpscaleOperationRepository;
use App\Tasks\Episodes\GetEpisode;

readonly class GetEpisodeAction
{
    public function __construct(private GetEpisode $getEpisode, private FacebookReelFile $facebookFile, private VideoUpscaleOperationRepository $exports) {}

    public function run(Episode $episode): EpisodeWorkspaceData
    {
        $workspace = $this->getEpisode->run($episode);

        if ($episode->acceptedAssembly !== null && $workspace->channels->contains('type', ChannelType::FACEBOOK)) {
            $workspace->facebookFileError = $this->facebookFile->exportError($episode->acceptedAssembly, $this->exports->forRelease($episode->acceptedAssembly));
        }

        return $workspace;
    }
}
