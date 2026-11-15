<?php

declare(strict_types=1);

namespace App\Actions;

use App\Classes\Story\Snapshot;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\ProjectContentMode;
use App\Events\AudioGenerating;
use App\Events\ImageGenerated;
use App\Events\PromptGenerated;
use App\Events\VideoGenerating;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Tasks\CreateAudio;
use App\Tasks\CreateBasePrompt;
use App\Tasks\CreateImage;
use App\Tasks\CreateVideo;
use App\Tasks\Production\UpdateProductionRun;
use App\Tasks\Prompts\CreatePrompt;
use App\Tasks\Prompts\UpdatePrompt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Запускает конвейер для одного проекта и сохраняет идентификаторы внешних задач.
 * Готовность видео и аудио обрабатывается отдельно через очередь и webhook.
 */
readonly class MakeShortSubAction
{
    public function __construct(
        private CreateBasePrompt $createBasePrompt,
        private CreatePrompt $createPrompt,
        private UpdatePrompt $updatePrompt,
        private CreateImage $createImage,
        private CreateVideo $createVideo,
        private CreateAudio $createAudio,
        private UpdateProductionRun $updateRun,
    ) {}

    public function run(Project $project, ?ProductionRun $run = null): Prompt
    {
        $this->assertActive($project, $run);

        if ($project->content_mode !== ProjectContentMode::SHORT) {
            throw ValidationException::withMessages(['project' => 'Series projects must produce episodes through automatic production.']);
        }

        $idea = $this->createBasePrompt->run($project);
        $prompt = DB::transaction(function () use ($project, $idea, $run): Prompt {
            $prompt = $this->createPrompt->run($project, $idea);

            if ($run instanceof ProductionRun) {
                $this->updateRun->run($run, ProductionStatus::RUNNING, ProductionStage::SCREENPLAY, attributes: ['prompt_id' => $prompt->id]);
            }

            return $prompt;
        });
        event(new PromptGenerated($prompt->prompt));

        $this->assertActive($project, $run);
        $imageUrl = $this->createImage->run($prompt->prompt);
        $this->updatePrompt->run($prompt, ['image_url' => $imageUrl]);
        event(new ImageGenerated($imageUrl));

        $this->assertActive($project, $run);
        $video = $this->createVideo->run($imageUrl, $prompt->prompt);
        $snapshot = ['video_request' => $video->request?->toArray()];
        $this->updatePrompt->run($prompt, [
            'video_id' => $video->id, 'video_provider' => $video->provider->value,
            'input_snapshot' => $snapshot, 'input_hash' => Snapshot::hash($snapshot),
        ]);
        event(new VideoGenerating($prompt));

        $this->assertActive($project, $run);
        $audioId = $this->createAudio->run($prompt->prompt, $video->audioSource);
        $this->updatePrompt->run($prompt, ['audio_id' => $audioId]);
        AudioGenerating::dispatchIf($audioId !== null, $prompt);

        return $prompt;
    }

    private function assertActive(Project $project, ?ProductionRun $run): void
    {
        if (! $project->refresh()->is_active || ($run instanceof ProductionRun && ! $run->refresh()->status->isActive())) {
            throw ValidationException::withMessages(['project' => 'Enable this project and start a new production before generating content.']);
        }
    }
}
