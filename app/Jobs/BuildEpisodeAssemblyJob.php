<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Episodes\BuildEpisodeAssemblyAction;
use App\Models\EpisodeAssembly;
use App\Tasks\Episodes\FindEpisodeAssembly;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildEpisodeAssemblyJob implements ShouldBeUnique, ShouldQueue
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
        return 'episode-assembly:' . $this->assemblyId;
    }

    public function handle(BuildEpisodeAssemblyAction $action, FindEpisodeAssembly $find): void
    {
        $assembly = $find->run($this->assemblyId);

        if ($assembly instanceof EpisodeAssembly) {
            $action->run($assembly);
        }
    }
}
