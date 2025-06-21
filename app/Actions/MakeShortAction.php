<?php

namespace App\Actions;

use App\Events\AudioGenerating;
use App\Events\ImageGenerated;
use App\Events\PromptGenerated;
use App\Events\VideoGenerating;
use App\Models\Project;
use App\Models\Prompt;
use App\Tasks\CreateAudio;
use App\Tasks\CreateBasePrompt;
use App\Tasks\CreateImage;
use App\Tasks\CreateVideo;

class MakeShortAction extends Action
{
    public function __construct(
        private readonly CreateBasePrompt $createBasePrompt,
        private readonly CreateImage $createImage,
        private readonly CreateVideo $createVideo,
        private readonly CreateAudio $createAudio,
    ) {
    }

    public function run(Project $project): void
    {
        $prompt = $this->generatePrompt($project);

        //$this->generateImage($prompt);
        $this->generateVideo($prompt);
        $this->generateAudio($prompt);
    }

    private function generatePrompt(Project $project): Prompt
    {
        $idea = $this->createBasePrompt->run($project);

        $prompt = $project->prompts()->create(['prompt' => $idea]);

        event(new PromptGenerated($prompt));

        return $prompt;
    }

    private function generateImage(Prompt $prompt): void
    {
        $imageUrl = $this->createImage->run($prompt->prompt);

        $prompt->update(['image_url' => $imageUrl]);

        event(new ImageGenerated($imageUrl));
    }

    private function generateVideo(Prompt $prompt): void
    {
        $videoId = $this->createVideo->run('', $prompt->prompt);

        $prompt->update(['video_id' => $videoId]);

        event(new VideoGenerating($prompt));
    }

    private function generateAudio(Prompt $prompt): void
    {
        $audioId = $this->createAudio->run($prompt->prompt);

        $prompt->update(['audio_id' => $audioId]);

        event(new AudioGenerating($prompt));
    }
}
