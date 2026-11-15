<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Data\VideoGenerationData;
use App\Exceptions\VideoGenerationUncertainException;
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
    ) {}

    public function run(string $imageUrl, string $idea): VideoGenerationData
    {
        $provider = $this->generators->primary();
        $fallbackProvider = $this->generators->fallback();
        $generator = $this->generators->make($provider);

        $prompt = <<<TEXT
Story: $idea

Reference role: The supplied image is the opening frame and the source of subject identity, environment, lighting, and color. Preserve these throughout the clip.

Shot 1 — continuous take: A gentle camera push-in keeps the subject and interacting object in frame. The action described in the story starts immediately, progresses through visible physical contact into its consequence, then settles into a brief, readable reaction. Keep the cause before the effect and give the payoff time to register. Maintain natural real-time movement, weight, balance, and contact with the ground.

Finish: Consistent anatomy and object shapes, coherent shadows, restrained background movement. Keep the composition readable on a vertical phone screen. No cuts, morphing, extra subjects, lettering, subtitles, logos, or watermarks. Tell the story visually without dialogue. If audio generation is supported, include natural synchronized environmental sounds and subtle action foley, without speech or background music.
TEXT;

        try {
            return new VideoGenerationData($generator->generate($imageUrl, $prompt), $provider, $generator->audioSource());
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

            return new VideoGenerationData($fallback->generate($imageUrl, $prompt), $fallbackProvider, $fallback->audioSource());
        }
    }
}
