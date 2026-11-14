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
        Cache::lock('prompt-build:' . $prompt->id, 3720)->get(function () use ($prompt): void {
            $this->refreshPrompt->run($prompt);

            if ($prompt->generated || ! $prompt->video_url || ! $prompt->audio_url) {
                return;
            }

            $this->saveGeneratedVideo->run($prompt, $this->buildVideo->run($prompt));

            event(new VideoBuilt($prompt));
        });
    }
}
