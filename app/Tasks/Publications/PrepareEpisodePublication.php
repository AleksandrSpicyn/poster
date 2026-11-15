<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Data\PublishVideoData;
use App\Models\Channel;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class PrepareEpisodePublication
{
    /** @return array{channel: Channel, video: PublishVideoData} */
    public function run(PublicationRequest $request): array
    {
        $assembly = EpisodeAssembly::query()->findOrFail($request->episode_assembly_id);
        $media = $assembly->getFirstMedia(EpisodeAssembly::VIDEO);

        if ($media === null || ! is_file($media->getPath())) {
            throw new RuntimeException('The accepted episode file is unavailable.');
        }

        return ['channel' => $request->channel()->firstOrFail(), 'video' => new PublishVideoData(
            $media->getPath(),
            URL::temporarySignedRoute('episodes.export', now()->addHours(2), ['assembly' => $assembly->id, 'media' => $media->id]),
            $request->payload['title'],
            $request->payload['description'],
            $assembly->id,
            $request->id,
            $request->payload['made_for_kids'],
        )];
    }
}
