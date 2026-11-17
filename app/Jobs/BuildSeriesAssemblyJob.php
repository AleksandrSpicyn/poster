<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Series\BuildSeriesAssemblyAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildSeriesAssemblyJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public int $uniqueFor = 60;

    public function __construct(public int $assemblyId)
    {
        $this->onQueue('render');
    }

    public function uniqueId(): string
    {
        return 'series-assembly:' . $this->assemblyId;
    }

    public function handle(BuildSeriesAssemblyAction $action): void
    {
        $action->run($this->assemblyId);
    }
}
