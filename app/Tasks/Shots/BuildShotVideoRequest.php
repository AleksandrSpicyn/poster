<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Classes\Billing\PilotShotQuote;
use App\Data\VideoGenerationRequestData;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\PromptRepository;
use App\Support\Integrations\Payload;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BuildShotVideoRequest
{
    public function __construct(private readonly PromptRepository $prompts, private readonly GenerationReservationRepository $usage) {}

    public function run(GenerationOperation $operation): VideoGenerationRequestData
    {
        $prompt = $this->prompts->forOperation($operation);
        $image = $prompt->getFirstMedia(Prompt::SHOT_IMAGE) ?? throw new RuntimeException('The private opening frame is missing.');
        $size = File::size($image->getPath());

        if ($size < 1 || $size >= 5 * 1024 * 1024) {
            throw new RuntimeException('The private opening frame must be smaller than 5 MB for the configured adapters.');
        }

        $configuration = $operation->request_snapshot['video'] ?? null;

        if (! is_array($configuration) || ! is_int($configuration['duration'] ?? null)) {
            throw new RuntimeException('The pinned video configuration is invalid.');
        }

        $frame = File::get($image->getPath());

        if ($this->usage->quotedSeconds($prompt->id) !== null) {
            PilotShotQuote::assertVerticalFrame($frame);
        }

        return new VideoGenerationRequestData(
            prompt: Payload::string($operation->request_snapshot['video_prompt'] ?? null, 'video_prompt'),
            duration: $configuration['duration'],
            model: Payload::string($configuration['model'] ?? null, 'model'),
            firstFrameUrl: 'data:' . ($image->mime_type ?? 'image/png') . ';base64,' . base64_encode($frame),
            resolution: Payload::string($configuration['resolution'] ?? null, 'resolution'),
            aspectRatio: Payload::string($configuration['aspect_ratio'] ?? null, 'aspect_ratio'),
            returnLastFrame: ($configuration['return_last_frame'] ?? null) === true,
            generateAudio: ($configuration['generate_audio'] ?? null) === true,
        );
    }
}
