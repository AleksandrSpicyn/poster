<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\AudioGenerator\SunoUnofficial\Data\TaskData;
use App\Events\AudioGenerated;
use App\Models\Prompt;
use Illuminate\Support\Facades\Log;

/**
 * Сопоставляет callback с Prompt по идентификатору аудиозадачи и сохраняет URL.
 * Повтор того же URL не порождает ещё одно событие готовности.
 */
class SaveAudio
{
    public function run(string $taskId, TaskData $task): void
    {
        if (! $task->audioUrl) {
            Log::info("Empty Audio URL: $taskId");

            return;
        }

        $prompt = Prompt::query()->firstWhere('audio_id', $taskId);

        if (! $prompt) {
            Log::info("Cannot find Audio by ID: $taskId");

            return;
        }

        if ($prompt->audio_url === $task->audioUrl) {
            return;
        }

        $prompt->update([
            'audio_url' => $task->audioUrl,
        ]);

        event(new AudioGenerated($prompt));
    }
}
