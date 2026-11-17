<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Data\Episodes\AcceptEpisodeData;
use App\Data\Production\EpisodeOutcomeData;
use App\Enums\AssemblyStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\SeriesOutputMode;
use App\Models\ProductionRun;
use App\Models\User;
use App\Tasks\Episodes\AcceptEpisode;
use App\Tasks\Production\InspectEpisodeOutcome;
use App\Tasks\Production\UpdateProductionRun;
use RuntimeException;

readonly class ConfirmSeriesStorySubAction
{
    public function __construct(
        private AdvanceSeriesCycleSubAction $cycle,
        private UpdateProductionRun $update,
        private InspectEpisodeOutcome $inspect,
        private AcceptEpisode $accept,
    ) {}

    public function run(ProductionRun $run, User $actor): void
    {
        $assembly = $run->assembly ?? throw new RuntimeException('The production montage is unavailable.');

        if ($assembly->status !== AssemblyStatus::ACCEPTED) {
            if ($run->outcome_snapshot === null) {
                $this->update->run($run, ProductionStatus::RUNNING);
                $outcome = $this->inspect->run($assembly);
                /** @var array<string, mixed> $snapshot */
                $snapshot = $outcome->toArray();
                $this->update->run($run, ProductionStatus::RUNNING, attributes: ['outcome_snapshot' => $snapshot]);
            } else {
                $outcome = EpisodeOutcomeData::validateAndCreate($run->outcome_snapshot);
            }

            if (! $outcome->usable) {
                $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: 'The montage needs review: ' . $outcome->review_notes);

                return;
            }

            $this->accept->run($assembly, new AcceptEpisodeData(
                $assembly->lock_version,
                $outcome->confirmed_events,
                $outcome->character_state,
                $outcome->open_threads ?? '',
                $outcome->scene_closed,
            ), $actor, $run);
        }

        if ($run->series?->output_mode === SeriesOutputMode::SINGLE_VIDEO) {
            $this->cycle->run($run);

            return;
        }

        $this->update->run($run, ProductionStatus::WAITING, ProductionStage::PUBLICATION);
    }
}
