<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Repositories\ProductionRunRepository;
use Illuminate\Support\Facades\DB;

readonly class AdvanceSeriesCursor
{
    public function __construct(private ProductionRunRepository $runs) {}

    public function run(ProductionRun $run): void
    {
        DB::transaction(function () use ($run): void {
            $current = $this->runs->findLockedOrFail($run->id);

            if (! $current->status->isActive() || $current->stage !== ProductionStage::STORY || $current->episode_id !== $run->episode_id) {
                return;
            }

            $this->runs->update($current, [
                'episode_id' => null, 'episode_assembly_id' => null, 'outcome_snapshot' => null,
                'stage' => ProductionStage::PREPARING, 'stage_started_at' => now(),
                'status' => ProductionStatus::WAITING, 'next_check_at' => now(), 'lock_version' => $current->lock_version + 1,
            ]);
        });
    }
}
