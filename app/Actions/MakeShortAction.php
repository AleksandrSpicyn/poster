<?php

namespace App\Actions;

use App\Jobs\CheckVideoCreation;
use App\Models\Project;
use App\Tasks\CreateBasePrompt;
use App\Tasks\CreateImage;
use App\Tasks\CreateVideo;

class MakeShortAction extends Action
{
    public function __construct(
        private readonly CreateBasePrompt $createBasePrompt,
        private readonly CreateImage $createImage,
        private readonly CreateVideo $createVideo,
    ) {
    }

    /* TODO Переделать на Pipeline */
    public function run(Project $project): void
    {
        $idea = $this->createBasePrompt->run($project);

        $prompt = $project->prompts()->create(['prompt' => $idea]);

        $image = $this->createImage->run($prompt);

        $prompt->update(['image_url' => $image]);

        $videoId = $this->createVideo->run($image, $prompt);

        $prompt->update(['video_url' => $videoId]);

        CheckVideoCreation::dispatch($videoId);

        /* TODO генерация аудио */
        /* TODO фабрика постер классов для всех типов их ChannelType */
    }
}
