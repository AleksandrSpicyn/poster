<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Models\ChannelMetricSync;
use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\ClippingSyncRun;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ClippingConnectionRepository;
use App\Repositories\ClippingSyncRunRepository;
use Illuminate\Database\Eloquent\Collection;

final readonly class GetClippingHistoryAction
{
    public function __construct(private ClippingConnectionRepository $connections, private ClippingSyncRunRepository $runs) {}

    /** @return array{imports:Collection<int,ClippingImport>, sources:Collection<int,OpusSource>, runs:Collection<int,ClippingSyncRun>, connections:Collection<int,ClippingConnection>, projects:Collection<int,Project>, metricStatuses:Collection<int,ChannelMetricSync>} */
    public function run(User $owner): array
    {
        $sources = $this->connections->recentSources($owner);
        $ids = array_values($sources->map(static fn(OpusSource $source): int => $source->project_id)->unique()->all());

        return [
            'imports' => $this->connections->history($owner),
            'sources' => $sources,
            'runs' => $this->runs->history($owner),
            'connections' => $this->connections->historyConnections($owner),
            'projects' => $this->connections->historyProjects($owner, $ids),
            'metricStatuses' => $this->runs->metricStatuses($owner),
        ];
    }
}
