<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\AudioGenerator\SunoUnofficial\Data\TaskData;
use App\Events\AudioGenerated;
use App\Models\Prompt;
use Illuminate\Support\Facades\Log;

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
