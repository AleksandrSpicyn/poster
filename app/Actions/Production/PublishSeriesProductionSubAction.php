<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Data\Episodes\PublishEpisodeData;
use App\Enums\ChannelType;
use App\Enums\ProductionStatus;
use App\Enums\PublicationStatus;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\ProductionRun;
use App\Models\User;
use App\Tasks\Production\UpdateProductionRun;
use App\Tasks\Publications\CreatePublicationRequest;
use RuntimeException;

readonly class PublishSeriesProductionSubAction
{
    public function __construct(
        private UpdateProductionRun $update,
        private CreatePublicationRequest $publish,
    ) {}

    public function run(ProductionRun $run, User $actor): void
    {
        $assembly = $run->assembly ?? throw new RuntimeException('The accepted montage is unavailable.');
        $channels = $run->project->channels->filter(fn(Channel $channel): bool => in_array($channel->type, [ChannelType::YOUTUBE, ChannelType::INSTAGRAM], true));
        $ids = array_values($channels->map(fn(Channel $channel): int => $channel->id)->all());
        $requests = $this->publish->run($assembly, new PublishEpisodeData($ids), $actor, $run);

        foreach ($requests as $request) {
            if ($request->status === PublicationStatus::PLANNED && $request->wasRecentlyCreated) {
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
