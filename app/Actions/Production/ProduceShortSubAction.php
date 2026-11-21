<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Actions\MakeShortSubAction;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\Channel;
use App\Models\ProductionRun;
use App\Tasks\Production\UpdateProductionRun;
use Illuminate\Support\Collection;

final readonly class ProduceShortSubAction
{
    public function __construct(private UpdateProductionRun $update, private MakeShortSubAction $short) {}

    /** @param Collection<int, Channel> $channels */
    public function run(ProductionRun $run, Collection $channels): void
    {
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
