<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SaveVideoAction;
use App\Models\Prompt;
use App\Tasks\Prompts\GetUnfinishedPrompts;
use Exception;
use Illuminate\Console\Command;

/**
 * CLI-точка повторной проверки незавершённых сборок через SaveVideoAction.
 * Только prompts с обоими исходниками действительно будут собраны.
 */
class SaveVideo extends Command
{
    protected $signature = 'app:save-video';

    /** @throws Exception */
    public function handle(SaveVideoAction $action, GetUnfinishedPrompts $getPrompts): void
    {
        $getPrompts->run()->each(fn(Prompt $prompt) => $action->run($prompt));
    }
}
