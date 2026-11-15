<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\VideoInputMode;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

class VideoCapabilitiesData extends Data
{
    /**
     * @param list<VideoInputMode> $modes
     * @param list<string> $resolutions
     * @param list<string> $aspectRatios
     * @param list<int> $durations
     */
    public function __construct(
        public string $model,
        public array $modes,
        public int $minDuration,
        public int $maxDuration,
        public array $resolutions,
        public string $defaultResolution,
        public string $defaultAspectRatio = 'adaptive',
        public array $aspectRatios = ['adaptive'],
        public int $maxReferenceImages = 0,
        public bool $supportsLastFrame = false,
        public bool $supportsReturnLastFrame = false,
        public bool $supportsAudioGeneration = false,
        public array $durations = [],
    ) {}

    public function assertSupports(VideoGenerationRequestData $request): void
    {
        $request->assertValid();

        if ($request->model !== $this->model || ! in_array($request->mode, $this->modes, true)
            || $request->duration < $this->minDuration || $request->duration > $this->maxDuration
            || ($this->durations !== [] && ! in_array($request->duration, $this->durations, true))
            || ! in_array($request->resolution, $this->resolutions, true)
            || ! in_array($request->aspectRatio, $this->aspectRatios, true)
            || count($request->referenceImageUrls) > $this->maxReferenceImages
            || ($request->lastFrameUrl !== null && ! $this->supportsLastFrame)
            || ($request->returnLastFrame && ! $this->supportsReturnLastFrame)
            || ($request->generateAudio && ! $this->supportsAudioGeneration)) {
            throw new InvalidArgumentException('The video model does not support the requested generation parameters.');
        }
    }
}
