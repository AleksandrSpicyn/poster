<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Data\VideoResultData;
use App\Enums\ReservationStatus;
use App\Enums\VideoAudioSource;
use App\Enums\VideoProvider;
use App\Enums\VideoReceiptStatus;
use App\Events\VideoGenerated;
use App\Exceptions\VideoGeneratingException;
use App\Exceptions\VideoGenerationPendingException;
use App\Models\Prompt;
use App\Repositories\GenerationReservationRepository;
use App\Tasks\Prompts\RefreshPrompt;
use App\Tasks\Prompts\UpdatePrompt;
use App\Tasks\ReadVideoGenerationResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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

    public function handle(VideoGeneratorFactory $generators, UpdatePrompt $updatePrompt, RefreshPrompt $refresh, ReadVideoGenerationResult $read, GenerationReservationRepository $reservations): void
    {
        $refresh->run($this->prompt);

        if ($this->prompt->shot_id !== null || $this->prompt->video_url || ! $this->prompt->video_id) {
            return;
        }

        try {
            $provider = $this->prompt->video_provider ?? VideoProvider::fromLegacy($this->alternative);
            $generator = $generators->make($provider);
            $seconds = $reservations->quotedSeconds($this->prompt->id);
            $model = $seconds === null ? null : data_get($this->prompt->input_snapshot, 'video_request.model');
            $result = $read->run($generator, $provider, $this->prompt->video_id, $this->prompt, is_string($model) ? $model : null);

            if (! $result instanceof VideoResultData) {
                $this->release(60);

                return;
            }

            if ($seconds !== null && $result->duration !== null && $result->duration !== $seconds) {
                throw new VideoGeneratingException('Длительность результата расходится с платным заказом.');
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
        } catch (VideoGeneratingException $e) {
            if (in_array($e->usage?->status, [VideoReceiptStatus::FAILED, VideoReceiptStatus::CANCELLED, VideoReceiptStatus::EXPIRED], true)) {
                DB::transaction(fn() => $reservations->setStatus([$this->prompt->id], ReservationStatus::RELEASED), 3);
            }

            $this->fail($e);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }
}
