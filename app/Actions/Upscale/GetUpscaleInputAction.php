<?php

declare(strict_types=1);

namespace App\Actions\Upscale;

use App\Enums\UpscaleOperationStatus;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\SeriesAssembly;
use App\Models\VideoUpscaleOperation;
use App\Repositories\MediaRepository;
use App\Repositories\UserRepository;

final readonly class GetUpscaleInputAction
{
    public function __construct(private MediaRepository $media, private UserRepository $users) {}

    public function run(VideoUpscaleOperation $operation, string $checksum): string
    {
        $assembly = $operation->source();
        $project = match (true) {
            $assembly instanceof Prompt => $assembly->project,
            $assembly instanceof SeriesAssembly => $assembly->series->project,
            default => $assembly->episodeRevision?->episode?->series?->project,
        };
        abort_unless($operation->source_media_id !== null
            && $project?->owner_id === $operation->initiator_id
            && $this->users->isVerified($operation->initiator_id)
            && $operation->source_sha256 === $checksum && in_array($operation->status, [
                UpscaleOperationStatus::SUBMITTING, UpscaleOperationStatus::SUBMITTED, UpscaleOperationStatus::PROCESSING, UpscaleOperationStatus::SUBMISSION_UNKNOWN,
            ], true), 404);
        $media = $this->media->findOrFail($operation->source_media_id);
        abort_unless($media->model_type === $assembly->getMorphClass() && $media->model_id === $assembly->id
            && $media->collection_name === EpisodeAssembly::UPSCALE_INPUT && $media->disk === 'local'
            && is_file($media->getPath()) && hash_file('sha256', $media->getPath()) === $checksum, 404);

        return $media->getPath();
    }
}
