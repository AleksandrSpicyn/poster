<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Classes\Billing\PilotShotQuote;
use App\Classes\Generators\VoiceSample;
use App\Data\AudioReferenceData;
use App\Data\VideoCapabilitiesData;
use App\Data\VideoGenerationRequestData;
use App\Enums\VideoInputMode;
use App\Models\CharacterRevision;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\Series;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PromptRepository;
use App\Support\Integrations\Payload;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BuildShotVideoRequest
{
    public function __construct(private readonly PromptRepository $prompts, private readonly GenerationReservationRepository $usage, private readonly MediaRepository $media) {}

    public function run(GenerationOperation $operation, VideoCapabilitiesData $capabilities): VideoGenerationRequestData
    {
        $prompt = $this->prompts->forOperation($operation);
        $image = $prompt->getFirstMedia(Prompt::SHOT_IMAGE) ?? throw new RuntimeException('The private opening frame is missing.');
        $replacement = $operation->result_snapshot['approved_frame_id'] ?? null;

        if (is_int($replacement)) {
            $image = $this->media->findOrFail($replacement);

            if ($image->model_type !== new Series()->getMorphClass() || (int) $image->model_id !== ($operation->request_snapshot['series_id'] ?? null)
                || $image->disk !== 'local' || $image->collection_name !== Series::ASSETS || ! str_starts_with($image->mime_type ?? '', 'image/')) {
                throw new RuntimeException('The approved opening frame changed.');
            }
        }

        $size = File::size($image->getPath());

        if ($size < 1 || $size >= 5 * 1024 * 1024) {
            throw new RuntimeException('The private opening frame must be smaller than 5 MB for the configured adapters.');
        }

        $configuration = $operation->request_snapshot['video'] ?? null;

        if (! is_array($configuration) || ! is_int($configuration['duration'] ?? null)) {
            throw new RuntimeException('The pinned video configuration is invalid.');
        }

        $frame = File::get($image->getPath());

        if (isset($operation->result_snapshot['frame_approved_at']) && hash('sha256', $frame) !== ($operation->result_snapshot['approved_frame_sha256'] ?? null)) {
            throw new RuntimeException('The approved opening frame changed.');
        }

        if ($this->usage->quotedSeconds($prompt->id) !== null) {
            PilotShotQuote::assertVerticalFrame($frame);
        }

        $audio = $this->audioReferences($operation, $capabilities);
        $imageUrl = 'data:' . ($image->mime_type ?? 'image/png') . ';base64,' . base64_encode($frame);

        return new VideoGenerationRequestData(
            prompt: Payload::string($operation->request_snapshot['video_prompt'] ?? null, 'video_prompt'),
            duration: $configuration['duration'],
            model: Payload::string($configuration['model'] ?? null, 'model'),
            mode: $audio === [] ? VideoInputMode::FIRST_FRAME : VideoInputMode::REFERENCES,
            firstFrameUrl: $audio === [] ? $imageUrl : null,
            referenceImageUrls: $audio === [] ? [] : [$imageUrl],
            resolution: Payload::string($configuration['resolution'] ?? null, 'resolution'),
            aspectRatio: Payload::string($configuration['aspect_ratio'] ?? null, 'aspect_ratio'),
            returnLastFrame: ($configuration['return_last_frame'] ?? null) === true,
            generateAudio: ($configuration['generate_audio'] ?? null) === true,
            audioReferences: $audio,
        );
    }

    /** @return list<AudioReferenceData> */
    private function audioReferences(GenerationOperation $operation, VideoCapabilitiesData $capabilities): array
    {
        $characters = $operation->request_snapshot['characters'] ?? [];

        if (! is_array($characters)) {
            throw new RuntimeException('The pinned cast is invalid.');
        }

        $samples = [];
        $durations = [];

        foreach ($characters as $character) {
            if (! is_array($character)) {
                throw new RuntimeException('The pinned character is invalid.');
            }

            $sample = $character['voice_sample'] ?? null;

            if ($sample === null) {
                continue;
            }

            if (! is_array($sample) || ! is_int($sample['media_id'] ?? null) || ! is_string($sample['sha256'] ?? null)
                || ! is_int($sample['duration_ms'] ?? null) || ! is_int($character['id'] ?? null)) {
                throw new RuntimeException('The pinned voice sample is invalid.');
            }

            $record = $this->media->findOrFail($sample['media_id']);

            if ($record->disk !== 'local' || ! in_array($record->collection_name, [CharacterRevision::VOICE_SAMPLE, CharacterRevision::PREVIOUS_VOICE_SAMPLES], true)
                || $record->model_type !== new CharacterRevision()->getMorphClass() || (int) $record->model_id !== $character['id']) {
                throw new RuntimeException('The pinned voice sample is unavailable.');
            }

            $bytes = File::get($record->getPath());

            if (hash('sha256', $bytes) !== $sample['sha256']) {
                throw new RuntimeException('The pinned voice sample changed.');
            }

            $samples[] = $bytes;
            $durations[] = $sample['duration_ms'];
        }

        $references = [];

        foreach ($capabilities->audioExcerptDurations($durations) as $index => $duration) {
            $bytes = $samples[$index];

            if ($duration < $durations[$index]) {
                $excerpt = VoiceSample::excerpt($bytes, $duration);
                $bytes = $excerpt->wav;
                $duration = $excerpt->durationMs;
            }

            $references[] = new AudioReferenceData('data:audio/wav;base64,' . base64_encode($bytes), $duration);
        }

        return $references;
    }
}
