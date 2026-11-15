<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\AudioGenerating;
use App\Events\ImageGenerated;
use App\Events\PromptGenerated;
use App\Events\VideoGenerating;
use App\Models\Project;
use App\Tasks\CreateAudio;
use App\Tasks\CreateBasePrompt;
use App\Tasks\CreateImage;
use App\Tasks\CreateVideo;
use App\Tasks\Prompts\CreatePrompt;
use App\Tasks\Prompts\UpdatePrompt;

/**
 * Запускает конвейер для одного проекта и сохраняет идентификаторы внешних задач.
 * Готовность видео и аудио обрабатывается отдельно через очередь и webhook.
 */
readonly class MakeShortAction
{
    public function __construct(
        private CreateBasePrompt $createBasePrompt,
        private CreatePrompt $createPrompt,
        private UpdatePrompt $updatePrompt,
        private CreateImage $createImage,
        private CreateVideo $createVideo,
        private CreateAudio $createAudio,
    ) {}

    public function run(Project $project): void
    {
        $prompt = $this->createPrompt->run($project, $this->createBasePrompt->run($project));
        event(new PromptGenerated($prompt->prompt));

        $imageUrl = $this->createImage->run($prompt->prompt);
        $this->updatePrompt->run($prompt, ['image_url' => $imageUrl]);
        event(new ImageGenerated($imageUrl));

        $video = $this->createVideo->run($imageUrl, $prompt->prompt);
        $this->updatePrompt->run($prompt, ['video_id' => $video->id, 'video_provider' => $video->provider->value]);
        event(new VideoGenerating($prompt));

        $audioId = $this->createAudio->run($prompt->prompt, $video->audioSource);
        $this->updatePrompt->run($prompt, ['audio_id' => $audioId]);
        AudioGenerating::dispatchIf($audioId !== null, $prompt);
    }
}
