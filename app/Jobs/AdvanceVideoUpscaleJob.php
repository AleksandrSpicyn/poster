<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Upscale\AdvanceVideoUpscaleAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class AdvanceVideoUpscaleJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly int $operationId)
    {
        $this->onQueue('render');
    }

    public function handle(AdvanceVideoUpscaleAction $action): void
    {
        Cache::lock('upscale:' . $this->operationId, 3700)->get(fn() => $action->run($this->operationId));
    }
}
