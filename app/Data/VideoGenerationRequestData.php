<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\VideoInputMode;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/** Параметры фиксируются до внешней отправки и сохраняются вместе с дублем. */
class VideoGenerationRequestData extends Data
{
    /**
     * @param list<string> $referenceImageUrls
     * @param list<AudioReferenceData> $audioReferences
     */
    public function __construct(
        public string $prompt,
        public int $duration,
        public string $model,
        public VideoInputMode $mode = VideoInputMode::FIRST_FRAME,
        public ?string $firstFrameUrl = null,
        public ?string $lastFrameUrl = null,
        public array $referenceImageUrls = [],
        public ?string $sourceVideoUrl = null,
        public string $resolution = '480p',
        public string $aspectRatio = 'adaptive',
        public bool $returnLastFrame = true,
        public bool $generateAudio = true,
        public array $audioReferences = [],
    ) {}

    public function assertValid(): void
    {
        if (trim($this->prompt) === '' || trim($this->model) === '' || $this->duration < 1) {
            throw new InvalidArgumentException('Video prompt, model and positive duration are required.');
        }

        $hasFirstFrame = $this->firstFrameUrl !== null && trim($this->firstFrameUrl) !== '';
        $hasSourceVideo = $this->sourceVideoUrl !== null && trim($this->sourceVideoUrl) !== '';

        $valid = match ($this->mode) {
            VideoInputMode::FIRST_FRAME => $hasFirstFrame && $this->referenceImageUrls === [] && $this->sourceVideoUrl === null,
            VideoInputMode::REFERENCES => $this->firstFrameUrl === null && $this->lastFrameUrl === null
                && $this->referenceImageUrls !== [] && $this->sourceVideoUrl === null,
            VideoInputMode::EXTEND => $hasSourceVideo && $this->firstFrameUrl === null && $this->lastFrameUrl === null,
        };

        if (($this->audioReferences !== [] && ($this->mode !== VideoInputMode::REFERENCES || ! $this->generateAudio)) || ! $valid || ($this->lastFrameUrl !== null && trim($this->lastFrameUrl) === '')
            || collect($this->audioReferences)->contains(static fn(AudioReferenceData $audio): bool => trim($audio->url) === '')
            || collect($this->referenceImageUrls)->contains(fn(string $url): bool => trim($url) === '')) {
            throw new InvalidArgumentException('Video inputs do not match the selected generation mode.');
        }
    }
}
