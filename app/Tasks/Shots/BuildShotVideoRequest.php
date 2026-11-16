<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Data\VideoGenerationRequestData;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Repositories\PromptRepository;
use App\Support\Integrations\Payload;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BuildShotVideoRequest
{
    public function __construct(private readonly PromptRepository $prompts) {}

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

        return new VideoGenerationRequestData(
            prompt: Payload::string($operation->request_snapshot['video_prompt'] ?? null, 'video_prompt'),
            duration: $configuration['duration'],
            model: Payload::string($configuration['model'] ?? null, 'model'),
            firstFrameUrl: 'data:' . ($image->mime_type ?? 'image/png') . ';base64,' . base64_encode(File::get($image->getPath())),
            resolution: Payload::string($configuration['resolution'] ?? null, 'resolution'),
            aspectRatio: Payload::string($configuration['aspect_ratio'] ?? null, 'aspect_ratio'),
            returnLastFrame: ($configuration['return_last_frame'] ?? null) === true,
            generateAudio: ($configuration['generate_audio'] ?? null) === true,
        );
    }
}
