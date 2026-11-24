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
use App\Exceptions\CodexBusyException;
use App\Exceptions\VideoGenerationUncertainException;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Repositories\ProductionBudgetRepository;
use App\Tasks\CreateAudio;
use App\Tasks\CreateBasePrompt;
use App\Tasks\CreateImage;
use App\Tasks\CreateVideo;
use App\Tasks\Production\AssertProductionActive;
use App\Tasks\Production\UpdateProductionRun;
use App\Tasks\Prompts\CreatePrompt;
use App\Tasks\Prompts\UpdatePrompt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Запускает конвейер для одного проекта и сохраняет идентификаторы внешних задач.
 * Готовность видео и аудио обрабатывается отдельно через очередь и webhook.
 */
readonly class MakeShortSubAction
{
    public function __construct(
        private ProductionBudgetRepository $budget,
        private CreateBasePrompt $createBasePrompt,
        private CreatePrompt $createPrompt,
        private UpdatePrompt $updatePrompt,
        private CreateImage $createImage,
        private CreateVideo $createVideo,
        private CreateAudio $createAudio,
        private UpdateProductionRun $updateRun,
        private AssertProductionActive $assertActive,
    ) {}

    public function run(Project $project, ?ProductionRun $run = null): Prompt
    {
        $this->assertActive->run($project, $run);

        if ($project->content_mode !== ProjectContentMode::SHORT) {
            throw ValidationException::withMessages(['project' => 'Series projects must produce episodes through automatic production.']);
        }

        $quote = $run instanceof ProductionRun ? $this->budget->claimShort($run) : null;

        try {
            $idea = $this->createBasePrompt->run($project);
            $prompt = DB::transaction(function () use ($project, $idea, $run): Prompt {
                $prompt = $this->createPrompt->run($project, $idea);

                if ($run instanceof ProductionRun) {
                    $current = $this->updateRun->run($run, ProductionStatus::RUNNING, ProductionStage::SCREENPLAY, attributes: ['prompt_id' => $prompt->id]);

                    if ($current->prompt_id !== $prompt->id) {
                        throw ValidationException::withMessages(['generation' => 'Состояние запуска изменилось во время подготовки идеи.']);
                    }

                    $this->budget->attachShort($current, $prompt->id);
                }

                return $prompt;
            });
            event(new PromptGenerated($prompt->prompt));

            $this->assertActive->run($project, $run);
            $imageUrl = $this->createImage->run($prompt->prompt);
            $this->updatePrompt->run($prompt, ['image_url' => $imageUrl]);
            event(new ImageGenerated($imageUrl));

            $this->assertActive->run($project, $run);
            $video = $this->createVideo->run($imageUrl, $prompt->prompt, $quote['video'] ?? null);
        } catch (VideoGenerationUncertainException $error) {
            throw $error;
        } catch (Throwable $error) {
            if ($quote !== null) {
                $this->budget->shortPreparationFailed($run, $error instanceof CodexBusyException);
            }

            throw $error;
        }

        $snapshot = ['video_request' => $video->request?->toArray()];
        $this->updatePrompt->run($prompt, [
            'video_id' => $video->id,
            'video_provider' => $video->provider->value,
            'input_snapshot' => $snapshot,
            'input_hash' => Snapshot::hash($snapshot),
        ]);
        event(new VideoGenerating($prompt));

        $this->assertActive->run($project, $run);
        $audioId = $this->createAudio->run($prompt->prompt, $video->audioSource);
        $this->updatePrompt->run($prompt, ['audio_id' => $audioId]);
        AudioGenerating::dispatchIf($audioId !== null, $prompt);

        return $prompt;
    }
}
