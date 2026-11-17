<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionStage;
use App\Models\ProductionRun;
use App\Models\User;

readonly class ProduceSeriesSubAction
{
    public function __construct(
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
