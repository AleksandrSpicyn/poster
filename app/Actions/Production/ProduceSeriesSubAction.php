<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Classes\Billing\ProductionBudget;
use App\Enums\ProductionStage;
use App\Models\ProductionRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class ProduceSeriesSubAction
{
    public function __construct(
        private ProductionBudget $budget,
        private PlanSeriesProductionSubAction $plan,
        private BuildSeriesVideoSubAction $finalVideo,
        private ConfirmSeriesVideoSubAction $finalReview,
        private PrepareSeriesProductionSubAction $prepare,
        private ProduceSeriesScreenplaySubAction $screenplay,
        private ProduceSeriesMediaSubAction $media,
        private ProduceSeriesMontageSubAction $montage,
        private ConfirmSeriesStorySubAction $story,
        private PublishSeriesProductionSubAction $publication,
    ) {}

    public function run(ProductionRun $run, User $actor): void
    {
        if (in_array($run->stage, [
            ProductionStage::PLANNING,
            ProductionStage::PREPARING,
            ProductionStage::SCREENPLAY,
            ProductionStage::MEDIA,
        ], true)) {
            DB::transaction(fn() => $this->budget->prepare($run->project, $run, $run->series), 3);
        }

        match ($run->stage) {
            ProductionStage::PLANNING => $this->plan->run($run),
            ProductionStage::FINAL_MONTAGE => $this->finalVideo->run($run),
            ProductionStage::FINAL_REVIEW => $this->finalReview->run($run),
            ProductionStage::PREPARING => $this->prepare->run($run, $actor),
            ProductionStage::SCREENPLAY => $this->screenplay->run($run, $actor),
            ProductionStage::MEDIA => $this->media->run($run, $actor),
            ProductionStage::MONTAGE => $this->montage->run($run),
            ProductionStage::STORY => $this->story->run($run, $actor),
            ProductionStage::PUBLICATION => $this->publication->run($run, $actor),
        };
    }
}
