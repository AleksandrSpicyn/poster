<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Data\Publications\PreparedPublicationData;
use App\Data\PublishVideoData;
use App\Data\Tiktok\TiktokPublicationData;
use App\Enums\PublicationProviderPhase;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use App\Models\SeriesAssembly;
use App\Repositories\ChannelRepository;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PromptRepository;
use App\Repositories\PublicationRequestRepository;
use App\Repositories\SeriesAssemblyRepository;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PrepareEpisodePublication
{
    public function __construct(
        private readonly MediaRepository $media,
        private readonly PublicationRequestRepository $publications,
        private readonly EpisodeAssemblyRepository $assemblies,
        private readonly ChannelRepository $channels,
        private readonly PromptRepository $prompts,
        private readonly SeriesAssemblyRepository $finalAssemblies,
    ) {}

    public function run(PublicationRequest $request): PreparedPublicationData
    {
        $request = $this->publications->findOrFail($request->id);

        if ($request->provider_phase === PublicationProviderPhase::FINISH_REQUESTED) {
            return new PreparedPublicationData($this->channels->forPublication($request), new PublishVideoData(
                '',
                '',
                $request->payload['title'],
                $request->payload['description'],
                $request->episode_assembly_id,
                $request->id,
            ), $request);
        }

        if (isset($request->payload['export_operation_id']) && ! isset($request->payload['media_id'], $request->payload['sha256'])) {
            throw new RuntimeException('Publication file has not been prepared.');
        }

        $options = isset($request->payload['tiktok']) ? TiktokPublicationData::from([...$request->payload['tiktok'], ...(isset($request->payload['media_id']) ? ['media_id' => $request->payload['media_id']] : [])]) : null;

        if ($request->prompt_id !== null) {
            $prompt = $this->prompts->forPublication($request);
            $media = isset($request->payload['media_id']) ? $this->media->episodeExport($prompt, $request->payload['media_id'], $request->payload['sha256'] ?? '') : $prompt->getFirstMedia();

            if (! $media instanceof Media || ($options instanceof TiktokPublicationData && $options->media_id !== $media->id) || ! is_file($media->getPath())) {
                throw new RuntimeException('The short video file is unavailable.');
            }

            return new PreparedPublicationData($this->channels->forPublication($request), new PublishVideoData(
                $media->getPath(),
                URL::temporarySignedRoute('prompts.export', now()->addHours(2), [
                    'prompt' => $prompt->id,
                    'media' => $media->id,
                    'owner' => $request->created_by,
                ]),
                $request->payload['title'],
                $request->payload['description'],
                null,
                $request->id,
                short: $prompt,
                tiktok: $options,
            ), $request);
        }

        $final = $request->series_assembly_id !== null;
        $assembly = $final ? $this->finalAssemblies->withContext($request->series_assembly_id) : $this->assemblies->withMedia($request->episode_assembly_id ?? throw new RuntimeException('No source.'));
        $media = isset($request->payload['media_id'])
            ? $this->media->episodeExport($assembly, $request->payload['media_id'], $request->payload['sha256'] ?? '')
            : $assembly->getFirstMedia($final ? SeriesAssembly::VIDEO : EpisodeAssembly::VIDEO);

        if (! $media instanceof Media || ($options instanceof TiktokPublicationData && $options->media_id !== $media->id) || ! is_file($media->getPath())) {
            throw new RuntimeException('The accepted episode file is unavailable.');
        }

        return new PreparedPublicationData($this->channels->forPublication($request), new PublishVideoData(
            $media->getPath(),
            URL::temporarySignedRoute($final ? 'series_assemblies.export' : 'episodes.export', now()->addHours(2), [
                'assembly' => $assembly->id,
                ...(isset($request->payload['sha256']) ? ['checksum' => $request->payload['sha256']] : []),
                'media' => $media->id,
                'owner' => $request->created_by,
            ]),
            $request->payload['title'],
            $request->payload['description'],
            $final ? null : $assembly->id,
            $request->id,
            $request->payload['made_for_kids'],
            $final ? $assembly->id : null,
            $request->payload['use_public_description'] ?? false,
            tiktok: $options,
        ), $request);
    }
}
