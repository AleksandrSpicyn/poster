<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReaders;
use App\Http\Resources\ProjectCardResource;
use App\Models\ClippingConnection;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ClippingConnectionRepository;

final readonly class GetClippingSettingsAction
{
    public function __construct(private ClippingConnectionRepository $connections, private ClippingReaders $readers, private GetClippingConnectionsAction $catalog) {}

    /** @return array<string, mixed> */
    public function run(ClippingConnection $connection, User $owner): array
    {
        return [
            ...$this->catalog->run($owner),
            'connection' => $connection->only([
                'id',
                'name',
                'driver',
                'catalog_checked_at',
                'catalog_error',
                'import_projects',
            ]),
            'lastAccess' => $connection->getAttribute('access_result'),
            'scope' => $connection->only([
                'import_projects',
                'import_accounts',
                'import_archives',
            ]),
            'supportsArchives' => $this->readers->supportsArchives($connection->driver),
            'supportsAccounts' => $this->readers->supportsAccounts($connection->driver),
            'projects' => $this->connections->linkedProjects($connection)->map(static fn(Project $project): array => [
                ...ProjectCardResource::make($project)->resolve(),
                'clips_count' => $project->getAttribute('clips_count'),
            ]),
        ];
    }
}
