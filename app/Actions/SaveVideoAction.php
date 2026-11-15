<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\VideoBuilt;
use App\Models\Prompt;
use App\Tasks\BuildVideo;
use App\Tasks\Prompts\RefreshPrompt;
use App\Tasks\Prompts\SaveGeneratedVideo;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

/**
 * Собирает готовый ролик из нормализованных видео- и аудиоисточников.
 * Повторные jobs проверяют актуальный Prompt под общей блокировкой сборки.
 */
readonly class SaveVideoAction
{
    public function __construct(
        private BuildVideo $buildVideo,
        private RefreshPrompt $refreshPrompt,
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
            $this->refreshPrompt->run($prompt);

            if ($prompt->generated || ! $prompt->video_url || ! $prompt->audio_url) {
                return;
            }

            $this->saveGeneratedVideo->run($prompt, $this->buildVideo->run($prompt));

            event(new VideoBuilt($prompt));
        });
    }
}
