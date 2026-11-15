<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Shots\SubmitShotGenerationAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SubmitShotGenerationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 480;

    public int $tries = 1;

    public function __construct(public readonly int $operationId) {}

    public function handle(SubmitShotGenerationAction $action): void
    {
        $action->run($this->operationId);
    }
}
