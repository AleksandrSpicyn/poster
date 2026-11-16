<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Data\Publications\PreparedPublicationData;
use App\Data\PublishVideoData;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use App\Repositories\ChannelRepository;
use App\Repositories\EpisodeAssemblyRepository;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PrepareEpisodePublication
{
    public function __construct(
        private readonly EpisodeAssemblyRepository $assemblies,
        private readonly ChannelRepository $channels,
    ) {}

    public function run(PublicationRequest $request): PreparedPublicationData
    {
        $assembly = $this->assemblies->withMedia($request->episode_assembly_id);
        $media = $assembly->getFirstMedia(EpisodeAssembly::VIDEO);

        if (! $media instanceof Media || ! is_file($media->getPath())) {
            throw new RuntimeException('The accepted episode file is unavailable.');
        }

        return new PreparedPublicationData($this->channels->forPublication($request), new PublishVideoData(
            $media->getPath(),
            URL::temporarySignedRoute('episodes.export', now()->addHours(2), ['assembly' => $assembly->id, 'media' => $media->id]),
            $request->payload['title'],
            $request->payload['description'],
            $assembly->id,
            $request->id,
            $request->payload['made_for_kids'],
        ), $request);
    }
}
