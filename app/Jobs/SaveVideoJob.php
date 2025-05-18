<?php

namespace App\Jobs;

use App\Actions\SaveVideoAction;
use App\Models\Prompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class SaveVideoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Prompt $prompt)
    {
    }

    /**
     * @throws FileIsTooBig
     * @throws FileDoesNotExist
     */
    public function handle(SaveVideoAction $action): void
    {
        $action->run($this->prompt);
    }
}
