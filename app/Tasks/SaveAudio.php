<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\AudioGenerator\SunoUnofficial\Data\TaskData;
use App\Events\AudioGenerated;
use App\Models\Prompt;
use App\Repositories\PromptRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сопоставляет callback с Prompt по идентификатору аудиозадачи и сохраняет URL.
 * Повтор того же URL не порождает ещё одно событие готовности.
 */
class SaveAudio
{
    public function __construct(private readonly PromptRepository $prompts) {}

    public function run(string $taskId, TaskData $task): void
    {
        if (! $task->audioUrl) {
            Log::info("Empty Audio URL: $taskId");

            return;
        }

        DB::transaction(function () use ($taskId, $task): void {
            $prompt = $this->prompts->lockForAudioTask($taskId);

            if (! $prompt instanceof Prompt) {
                Log::info("Cannot find Audio by ID: $taskId");

                return;
            }

            if ($prompt->audio_url === $task->audioUrl) {
                return;
            }

            $this->prompts->update($prompt, [
                'audio_url' => $task->audioUrl,
            ]);

            event(new AudioGenerated($prompt));
        }, 3);
    }
}
