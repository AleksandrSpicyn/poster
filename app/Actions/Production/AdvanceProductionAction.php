<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Actions\MakeShortSubAction;
use App\Enums\ChannelType;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\ProjectContentMode;
use App\Models\Channel;
use App\Tasks\Production\GetProductionRun;
use App\Tasks\Production\StopProductionRun;
use App\Tasks\Production\UpdateProductionRun;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Gate;

readonly class AdvanceProductionAction
{
    public function __construct(
        private GetProductionRun $getRun,
        private UpdateProductionRun $update,
        private StopProductionRun $stop,
        private ProduceSeriesSubAction $series,
        private MakeShortSubAction $short,
        #[Config('production.run_timeout_minutes')] private int $timeoutMinutes,
    ) {}

    public function run(int $id): void
    {
        $run = $this->getRun->run($id);

        if (! $run->status->isActive()) {
            return;
        }

        if (! $run->project->is_active) {
            $this->stop->run($run, 'The project is disabled.');

            return;
        }

        $expectedMode = $run->series_id === null ? ProjectContentMode::SHORT : ProjectContentMode::SERIES;

        if ($run->project->content_mode !== $expectedMode) {
            $this->stop->run($run, 'The project content type changed.');

            return;
        }

        $actor = $run->actor;

        if ($actor === null || ! Gate::forUser($actor)->allows('manageSeries')) {
            $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: 'The production operator no longer has permission.');

            return;
        }

        if ($run->started_at?->lessThan(now()->subMinutes($this->timeoutMinutes))) {
            $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: 'Production exceeded its recovery window. Review the existing operations before resuming.');

            return;
        }

        if ($run->series_id !== null && ($run->series === null || ! $run->series->automation_enabled || $run->series->archived_at !== null)) {
            $this->stop->run($run, 'Series automation is paused.');

            return;
        }

        $channels = $run->project->channels->filter(fn(Channel $channel): bool => in_array($channel->type, [ChannelType::YOUTUBE, ChannelType::INSTAGRAM], true));

        if ($channels->isEmpty() || $channels->contains(fn(Channel $channel): bool => ! filled($channel->credentials))) {
            $this->update->run($run, ProductionStatus::WAITING, message: 'Connect and authorise the project publishing channels before production starts.');

            return;
        }

        if ($run->series_id !== null) {
            $this->series->run($run, $actor);

            return;
        }

        if ($run->prompt_id === null) {
            if ($run->stage !== ProductionStage::PREPARING) {
                $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: 'The standalone submission was interrupted. Inspect its outcome before another request.');

                return;
            }

            $this->update->run($run, ProductionStatus::RUNNING, ProductionStage::SCREENPLAY);
            $this->short->run($run->project, $run);
            $this->update->run($run, ProductionStatus::WAITING, ProductionStage::MEDIA);

            return;
        }

        if ($run->stage === ProductionStage::SCREENPLAY) {
            $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: 'The standalone submission was interrupted. Inspect the existing prompt before another request.');

            return;
        }

        if (! $run->prompt?->generated) {
            $this->update->run($run, ProductionStatus::WAITING, ProductionStage::MEDIA);

            return;
        }

        $publishedChannels = $run->prompt->posts->pluck('channel_id');
        $complete = $channels->every(fn(Channel $channel): bool => $publishedChannels->contains($channel->id));
        $this->update->run($run, $complete ? ProductionStatus::COMPLETED : ProductionStatus::WAITING, ProductionStage::PUBLICATION);
    }
}
