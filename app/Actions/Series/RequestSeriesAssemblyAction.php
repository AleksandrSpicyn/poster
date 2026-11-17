<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Jobs\BuildSeriesAssemblyJob;
use App\Models\Series;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Tasks\Production\AttachSeriesAssembly;
use App\Tasks\Series\SaveSeriesAssembly;
use Illuminate\Support\Facades\Gate;

readonly class RequestSeriesAssemblyAction
{
    public function __construct(private SaveSeriesAssembly $save, private AttachSeriesAssembly $attach) {}

    public function run(Series $series, User $actor): SeriesAssembly
    {
        Gate::forUser($actor)->authorize('manageSeries');
        $assembly = $this->save->run($series);
        $this->attach->run($assembly);
        BuildSeriesAssemblyJob::dispatch($assembly->id)->afterCommit();

        return $assembly;
    }
}
