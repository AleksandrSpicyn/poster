<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\AssemblyStatus;
use App\Models\EpisodeAssembly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GetEpisodeAssembliesForReconciliation
{
    /** @return Collection<int, EpisodeAssembly> */
    public function run(int $limit): Collection
    {
        return DB::transaction(function () use ($limit): Collection {
            $assemblies = EpisodeAssembly::query()
                ->where(function (Builder $query): void {
                    $query->where(function (Builder $queued): void {
                        $queued->where('status', AssemblyStatus::QUEUED)->where('updated_at', '<=', now()->subSeconds(90));
                    })->orWhere(function (Builder $building): void {
                        $building->where('status', AssemblyStatus::BUILDING)->where('started_at', '<=', now()->subSeconds(3720));
                    });
                })
                ->orderBy('updated_at')
                ->limit(max(1, min(100, $limit)))
                ->lockForUpdate()
                ->get();

            foreach ($assemblies as $assembly) {
                $assembly->update([
                    'status' => AssemblyStatus::QUEUED, 'started_at' => null,
                    'lock_version' => $assembly->lock_version + 1,
                ]);
            }

            return $assemblies;
        });
    }
}
