<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Data\VideoResultData;
use App\Enums\VideoAudioSource;
use App\Enums\VideoProvider;
use App\Events\VideoGenerated;
use App\Exceptions\VideoGeneratingException;
use App\Exceptions\VideoGenerationPendingException;
use App\Models\Prompt;
use App\Tasks\Prompts\RefreshPrompt;
use App\Tasks\Prompts\UpdatePrompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Опрашивает провайдера, выбранного при запуске видео, до получения URL.
 * Не готовый результат откладывает job на минуту; исключение завершает её ошибкой.
 */
class CheckVideoCreation implements ShouldQueue
{
    use Queueable;

    /** Значение по умолчанию нужно старым payload, где оно не сериализовалось. */
    public int $tries = 10;

    public function __construct(
        private readonly Prompt $prompt,
        private readonly bool $alternative = false,
        int $maxAttempts = 10,
    ) {
        $this->tries = max(1, $maxAttempts);
    }

    public function handle(VideoGeneratorFactory $generators, UpdatePrompt $updatePrompt, RefreshPrompt $refresh): void
    {
        $refresh->run($this->prompt);

        if ($this->prompt->shot_id !== null || $this->prompt->video_url || ! $this->prompt->video_id) {
            return;
        }

        try {
            $provider = $this->prompt->video_provider ?? VideoProvider::fromLegacy($this->alternative);
            $generator = $generators->make($provider);
            $result = $generator->check($this->prompt->video_id);

            if (! $result instanceof VideoResultData) {
                $this->release(60);

                return;
            }

            if ($generator->audioSource() === VideoAudioSource::EMBEDDED && $result->audioUrl === null) {
                throw new VideoGeneratingException('The standalone video is missing its requested embedded audio.');
            }

            $updatePrompt->run($this->prompt, array_filter([
                'video_url' => $result->videoUrl,
                'audio_url' => $result->audioUrl,
            ], static fn(?string $value): bool => $value !== null));

            event(new VideoGenerated($this->prompt));
        } catch (VideoGenerationPendingException) {
            $this->release(60);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }
}
