<?php

namespace App\Console\Commands;

use App\Actions\SaveVideoAction;
use App\Models\Prompt;
use Illuminate\Console\Command;

class SaveVideo extends Command
{
    protected $signature = 'app:save-video';

    public function handle(SaveVideoAction $action): void
    {
        Prompt::where('generated', false)
            ->each(fn(Prompt $prompt) => $action->run($prompt));
    }
}
