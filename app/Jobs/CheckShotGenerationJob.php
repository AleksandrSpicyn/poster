<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Shots\CheckShotGenerationAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckShotGenerationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    public int $tries = 3;

    public function __construct(public readonly int $operationId) {}

    public function handle(CheckShotGenerationAction $action): void
    {
        $action->run($this->operationId);
    }
}
