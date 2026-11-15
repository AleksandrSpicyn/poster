<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use Illuminate\Support\Facades\DB;

class UpdateProductionRun
{
    /** @param array{episode_id?: int, prompt_id?: int, episode_assembly_id?: int, outcome_snapshot?: array<string, mixed>} $attributes */
    public function run(ProductionRun $run, ProductionStatus $status, ?ProductionStage $stage = null, ?string $message = null, array $attributes = []): ProductionRun
    {
        return DB::transaction(function () use ($run, $status, $stage, $message, $attributes): ProductionRun {
            $current = ProductionRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($current->status === ProductionStatus::STOPPED) {
                return $run->refresh();
            }

            $nextStage = $stage ?? $current->stage;
            $run->fill([
                ...$attributes,
                'status' => $status,
                'stage' => $nextStage,
                'error_message' => $message,
                'started_at' => $current->started_at ?? ($nextStage === ProductionStage::PREPARING ? null : now()),
                'next_check_at' => $status->isActive() ? now()->addSeconds(15) : null,
                'completed_at' => in_array($status, [ProductionStatus::COMPLETED, ProductionStatus::STOPPED], true) ? now() : null,
            ])->save();

            return $run;
        });
    }
}
