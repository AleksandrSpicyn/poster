<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Data\Episodes\PublishEpisodeData;
use App\Enums\ChannelType;
use App\Enums\ProductionStatus;
use App\Enums\PublicationStatus;
use App\Enums\SeriesOutputMode;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\ProductionRun;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Tasks\Production\UpdateProductionRun;
use App\Tasks\Publications\CreatePublicationRequest;
use App\Tasks\Publications\CreateSeriesPublication;
use RuntimeException;

readonly class PublishSeriesProductionSubAction
{
    public function __construct(
        private UpdateProductionRun $update,
        private CreatePublicationRequest $publish,
        private CreateSeriesPublication $publishFinal,
    ) {}

    public function run(ProductionRun $run, User $actor): void
    {
        $final = $run->series?->output_mode === SeriesOutputMode::SINGLE_VIDEO;
        $assembly = ($final ? $run->seriesAssembly : $run->assembly) ?? throw new RuntimeException('The accepted montage is unavailable.');
        $channels = $run->project->channels->filter(fn(Channel $channel): bool => $channel->is_active && in_array($channel->type, [ChannelType::YOUTUBE, ChannelType::INSTAGRAM], true));
        $ids = array_values($channels->map(fn(Channel $channel): int => $channel->id)->all());
        $requests = $assembly instanceof SeriesAssembly
            ? $this->publishFinal->run($assembly, PublishEpisodeData::validateAndCreate(['channel_ids' => data_get($run->input_snapshot, 'channel_ids', [])]), $actor, $run)
            : $this->publish->run($assembly, new PublishEpisodeData($ids), $actor, $run);

        foreach ($requests as $request) {
            if ($request->status === PublicationStatus::PLANNED) {
                PublishEpisodeJob::dispatch($request->id)->afterCommit();
            }
        }

        if ($requests->isNotEmpty() && $requests->every(fn($request): bool => $request->status === PublicationStatus::PUBLISHED)) {
            $this->update->run($run, ProductionStatus::COMPLETED);

            return;
        }

        $blocked = $requests->contains(fn($request): bool => in_array($request->status, [PublicationStatus::FAILED, PublicationStatus::SUBMISSION_UNKNOWN], true));
        $this->update->run(
            $run,
            $blocked ? ProductionStatus::NEEDS_ATTENTION : ProductionStatus::WAITING,
            message: $blocked ? 'A publication needs attention. Keep its existing receipt; do not send a duplicate.' : null,
        );
    }
}
