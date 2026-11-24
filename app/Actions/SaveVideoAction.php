<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReservationStatus;
use App\Events\VideoBuilt;
use App\Models\Prompt;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\PromptRepository;
use App\Tasks\BuildVideo;
use App\Tasks\Prompts\SaveGeneratedVideo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Собирает готовый ролик из нормализованных видео- и аудиоисточников.
 * Повторные jobs проверяют актуальный Prompt под общей блокировкой сборки.
 */
readonly class SaveVideoAction
{
    public function __construct(
        private BuildVideo $buildVideo,
        private GenerationReservationRepository $usage,
        private PromptRepository $prompts,
        private SaveGeneratedVideo $saveGeneratedVideo,
    ) {}

    /**
     * @throws FileIsTooBig
     * @throws FileDoesNotExist
     */
    public function run(Prompt $prompt): void
    {
        if ($prompt->shot_id !== null) {
            return;
        }

        // TTL превышает часовой timeout SaveVideoJob; оба события готовности используют один lock.
        Cache::lock('prompt-build:' . $prompt->id, 3720)->get(function () use ($prompt): void {
            // Payload job мог устареть, пока второй исходник сохранялся другим worker.
            $this->prompts->refreshForAssembly($prompt);

            if ($prompt->generated || ! $prompt->video_url || ! $prompt->audio_url || $prompt->project?->is_active !== true) {
                return;
            }

            $existing = $prompt->getFirstMedia();

            if ($existing instanceof Media) {
                $this->saveGeneratedVideo->verifyAttachment($prompt, $existing);
            } else {
                $this->buildVideo->run($prompt, function (string $path) use ($prompt): void {
                    $this->saveGeneratedVideo->run($prompt, $path);
                });
            }

            DB::transaction(function () use ($prompt): void {
                $current = $this->prompts->findLockedOrFail($prompt->id);
                $this->usage->assertDeliverable($current->id);
                $this->prompts->update($current, ['generated' => true]);
                $this->usage->transitionTakeReservations([$current->id], ReservationStatus::CONSUMED);
                $this->prompts->reloadMedia($current);
                event(new VideoBuilt($current));
            });
        });
    }
}
