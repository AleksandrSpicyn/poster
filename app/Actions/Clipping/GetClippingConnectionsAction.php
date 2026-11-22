<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReaders;
use App\Http\Resources\ProjectResource;
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
use Illuminate\Support\Arr;

final readonly class GetClippingConnectionsAction
{
    public function __construct(private ClippingConnectionRepository $connections, private ClippingReaders $readers, private ClippingSyncRunRepository $runs) {}

    /** @return array<string, mixed> */
    public function run(User $owner): array
    {
        return ['connections' => $this->connections->owned($owner)];
    }

    /** @return array<string, mixed> */
    public function settings(ClippingConnection $connection, User $owner): array
    {
        return [
            ...$this->run($owner),
            'connection' => $connection->only(['id', 'name', 'driver']),
            'drivers' => $this->readers->labels(),
            'lastAccess' => $connection->getAttribute('access_result'),
            'scope' => $connection->only(['import_projects', 'import_accounts', 'import_archives']),
            'supportsArchives' => $this->readers->supportsArchives($connection->driver),
            'supportsAccounts' => $this->readers->supportsAccounts($connection->driver),
            'projects' => $this->connections->linkedProjects($connection)->map(static fn(Project $project): array => [
                ...Arr::only(ProjectResource::make($project)->resolve(), ['id', 'name', 'cover']),
                'clips_count' => $project->getAttribute('clips_count'),
            ]),
        ];
    }

    /** @return array{imports:Collection<int,ClippingImport>, sources:Collection<int,OpusSource>, runs:Collection<int,ClippingSyncRun>, connections:Collection<int,ClippingConnection>, projects:Collection<int,Project>, metricStatuses:Collection<int,ChannelMetricSync>} */
    public function history(User $owner): array
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
