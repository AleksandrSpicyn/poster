<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Repositories\ProductionRunRepository;
use Illuminate\Support\Facades\DB;

class UpdateProductionRun
{
    public function __construct(private readonly ProductionRunRepository $runs) {}

    /** @param array{episode_id?: int, prompt_id?: int, series_assembly_id?: int, episode_assembly_id?: int, outcome_snapshot?: array<string, mixed>} $attributes */
    public function run(ProductionRun $run, ProductionStatus $status, ?ProductionStage $stage = null, ?string $message = null, array $attributes = []): ProductionRun
    {
        return DB::transaction(function () use ($run, $status, $stage, $message, $attributes): ProductionRun {
            $current = $this->runs->findLockedOrFail($run->id);

            if ($current->status === ProductionStatus::STOPPED || $current->lock_version !== $run->lock_version) {
                return $this->runs->refresh($run);
            }

            $nextStage = $stage ?? $current->stage;
            $this->runs->save($run->fill([
                ...$attributes,
                'status' => $status,
                'lock_version' => $current->lock_version + 1,
                'stage_started_at' => $nextStage === $current->stage ? $current->stage_started_at : now(),
                'stage' => $nextStage,
                'error_message' => $message,
                'started_at' => $current->started_at ?? ($nextStage === ProductionStage::PREPARING ? null : now()),
                'next_check_at' => $status->isActive() ? now()->addSeconds(15) : null,
                'completed_at' => in_array($status, [ProductionStatus::COMPLETED, ProductionStatus::STOPPED], true) ? now() : null,
            ]));

            return $run;
        });
    }
}
