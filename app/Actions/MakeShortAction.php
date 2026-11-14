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
        $this->updatePrompt->run($prompt, ['video_id' => $video->id]);
        event(new VideoGenerating($prompt, $video->alternative));

        $this->updatePrompt->run($prompt, ['audio_id' => $this->createAudio->run($prompt->prompt)]);
        event(new AudioGenerating($prompt));
    }
}
