<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\VideoGenerator\VideoGenerator;
use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Data\VideoGenerationData;
use App\Data\VideoGenerationRequestData;
use App\Exceptions\VideoGenerationUncertainException;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Запускает видео у основного провайдера и при ошибке запуска пробует резервный.
 * Фактический провайдер сохраняется с task ID и не зависит от смены конфигурации.
 */
readonly class CreateVideo
{
    public function __construct(
        private VideoGeneratorFactory $generators,
        private GeneralSettings $generalSettings,
    ) {}

    public function run(string $imageUrl, string $idea): VideoGenerationData
    {
        $provider = $this->generators->primary();
        $fallbackProvider = $this->generators->fallback();
        $generator = $this->generators->make($provider);
        $duration = $this->generalSettings->duration;

        $prompt = <<<TEXT
Story: $idea

Duration: $duration seconds. Complete the entire story within this time, including the final payoff.

Reference role: The supplied image is the first frame and the source of subject identity, props, environment, lighting, and color. Preserve them throughout the clip.

Visual hook: Start the action in the first second, directly from the opening problem shown in the image. Make the subject's goal and the imminent comic setback immediately clear. No idle establishing shot, slow reveal, or decorative movement.
Escalation: In one continuous take, perform the story's connected action beats in order. Each attempt changes the situation and increases urgency. Use energetic, readable movement with natural weight, momentum, and physical contact. A purposeful tracking camera follows the action and keeps the subject, obstacle, and goal visible; avoid unnecessary camera motion and slow motion.
Payoff: Complete the story's surprising reversal before the clip ends, then hold a brief, readable reaction. Show cause before effect and the outcome clearly. Do not replace the story with a repeated gesture or an unrelated gag.

Soundtrack: Generate audible instrumental background music from the first frame, continuing under the whole scene. Use a playful, propulsive rhythmic groove with percussion, bass, and short plucked or brass accents. Build the musical tension with the action, punctuate the reversal with a musical accent, and end with a short resolved musical button. Mix synchronized action foley over the music while keeping the music clearly audible; environmental sounds remain secondary. No speech, voiceover, narration, commentary, dialogue, lyrics, singing, or intelligible vocals. Never read or describe the story aloud.

Continuity: Consistent anatomy, prop shapes, coherent shadows, and contact with the ground. Keep the composition readable on a vertical phone screen. No cuts, morphing, extra subjects, lettering, subtitles, logos, or watermarks. The action and payoff must remain understandable with sound off.
TEXT;

        $request = $this->request($generator, $imageUrl, $prompt, $duration);
        $generator->capabilities($request->model)->assertSupports($request);

        try {
            return new VideoGenerationData($generator->generate($request), $provider, $generator->audioSource(), $request);
        } catch (VideoGenerationUncertainException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            if ($provider === $fallbackProvider) {
                throw $throwable;
            }

            Log::warning('Using fallback video generator.', [
                'provider' => $provider->value,
                'fallback' => $fallbackProvider->value,
                'exception' => $throwable::class,
            ]);

            $fallback = $this->generators->make($fallbackProvider);
            $fallbackRequest = $this->request($fallback, $imageUrl, $prompt, $duration);

            return new VideoGenerationData($fallback->generate($fallbackRequest), $fallbackProvider, $fallback->audioSource(), $fallbackRequest);
        }
    }

    private function request(VideoGenerator $generator, string $imageUrl, string $prompt, int $duration): VideoGenerationRequestData
    {
        $capabilities = $generator->capabilities();

        return new VideoGenerationRequestData(
            prompt: $prompt,
            duration: $duration,
            model: $capabilities->model,
            firstFrameUrl: $imageUrl,
            resolution: $capabilities->defaultResolution,
            aspectRatio: $capabilities->defaultAspectRatio,
            returnLastFrame: $capabilities->supportsReturnLastFrame,
            generateAudio: $capabilities->supportsAudioGeneration,
        );
    }
}
