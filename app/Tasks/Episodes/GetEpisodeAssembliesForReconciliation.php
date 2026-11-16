<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\AssemblyStatus;
use App\Models\EpisodeAssembly;
use App\Repositories\EpisodeAssemblyRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GetEpisodeAssembliesForReconciliation
{
    public function __construct(private readonly EpisodeAssemblyRepository $assemblies) {}

    /** @return Collection<int, EpisodeAssembly> */
    public function run(int $limit): Collection
    {
        return DB::transaction(function () use ($limit): Collection {
            $assemblies = $this->assemblies->lockForReconciliation($limit);

            foreach ($assemblies as $assembly) {
                $this->assemblies->update($assembly, [
                    'status' => AssemblyStatus::QUEUED, 'started_at' => null,
                    'lock_version' => $assembly->lock_version + 1,
                ]);
            }

            return $assemblies;
        });
    }
}
