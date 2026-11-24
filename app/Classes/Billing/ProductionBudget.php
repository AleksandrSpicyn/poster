<?php

declare(strict_types=1);

namespace App\Classes\Billing;

use App\Enums\ReservationStatus;
use App\Enums\RevisionStatus;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Repositories\GenerationCreditRepository;
use App\Repositories\ProductionBudgetRepository;
use Illuminate\Validation\ValidationException;

final readonly class ProductionBudget
{
    public function __construct(private GenerationCreditRepository $credits, private ProductionBudgetRepository $budgets, private ProductionQuoteCalculator $quotes) {}

    public function prepare(Project $project, ProductionRun $run, ?Series $series): void
    {
        $owner = $project->owner_id;

        if ($owner === null || ! $this->budgets->lockEnforcedAccount($owner)) {
            return;
        }

        $latest = $series?->episodes->first();
        $episode = $run->episode ?? ($latest?->accepted_assembly_id === null ? $latest : null);
        $number = $episode->number ?? (($latest->number ?? 0) + 1);

        if ($series?->episode_limit !== null && $number > $series->episode_limit) {
            return;
        }

        $key = $series instanceof Series ? 'episode:' . $run->id . ':' . $number : 'short:' . $run->id;
        $revision = $episode?->currentRevision;
        $revision = $revision?->status === RevisionStatus::APPROVED ? $revision : null;

        $budget = $this->budgets->lockBudgetForIntent($owner, $key);

        if ($budget !== null && ($revision === null || $budget->episode_revision_id === $revision->id)) {
            return;
        }

        if ($budget !== null && $budget->episode_revision_id !== null) {
            throw ValidationException::withMessages(['balance' => 'Версия сценария изменилась. Остановите этот запуск и подтвердите резерв новой версии.']);
        }

        $quote = $this->quotes->calculate($series, $revision);
        $id = $budget->id ?? $this->budgets->createReservation($project, $run, $key, $quote->reservationSnapshot(), null);

        if ($budget === null && $quote->seconds > 0) {
            $this->credits->reserve($owner, $id, $key, null, null, $quote->seconds);
        }

        if ($revision === null) {
            return;
        }

        foreach ($quote->shots as $shot => $duration) {
            $shotKey = 'episode-shot:' . $run->id . ':' . $shot;
            $child = $this->budgets->createReservation($project, $run, $shotKey, [
                'seconds' => $duration,
                'shot_id' => $shot,
                'video' => [...$quote->video, 'duration' => $duration],
            ], $revision->id);
            $this->credits->assign($owner, $id, $child, $shot, $duration);
        }

        $this->credits->settle($owner, $id, ReservationStatus::RELEASED);
        $this->budgets->markAllocated($id, $revision->id);
    }
}
