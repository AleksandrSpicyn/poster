<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Models\EpisodeRevision;
use App\Models\Scene;
use Illuminate\Validation\ValidationException;

readonly class AssertProductionScreenplay
{
    public function __construct(private VideoGeneratorFactory $generators) {}

    public function run(EpisodeRevision $revision): void
    {
        $revision->loadMissing('scenes.shots');
        $shots = $revision->scenes->flatMap(fn(Scene $scene) => $scene->shots);
        $duration = data_get($revision->context_snapshot, 'specification.episode_duration_seconds');
        $capabilities = $this->generators->make($this->generators->primary())->capabilities();

        if (! is_int($duration) || $shots->isEmpty() || $shots->sum('duration_ms') !== $duration * 1000) {
            throw ValidationException::withMessages(['screenplay' => 'The automatic screenplay must match the configured episode duration exactly. Review the draft.']);
        }

        foreach ($shots as $shot) {
            $seconds = intdiv($shot->duration_ms, 1000);

            if ($shot->duration_ms % 1000 !== 0 || $seconds < $capabilities->minDuration || $seconds > $capabilities->maxDuration
                || ($capabilities->durations !== [] && ! in_array($seconds, $capabilities->durations, true))
                || ($shot->dialogue !== [] && ! $capabilities->supportsAudioGeneration)) {
                throw ValidationException::withMessages(['screenplay' => 'A screenplay shot is incompatible with the video model. Review the draft before generating media.']);
            }
        }
    }
}
