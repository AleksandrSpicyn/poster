<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Upscale\RequestVideoUpscaleAction;
use App\Data\Upscale\RequestVideoUpscaleData;
use App\Enums\ChannelType;
use App\Enums\UpscaleExport;
use App\Events\VideoBuilt;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Repositories\PublicationRequestRepository;
use App\Tasks\Projects\GetPromptProject;

readonly class VideoBuiltListener
{
    public function __construct(private RequestVideoUpscaleAction $upscale, private PublicationRequestRepository $requests, private GetPromptProject $getProject) {}

    public function handle(VideoBuilt $event): void
    {
        $prompt = $event->getPrompt();
        $project = $this->getProject->run($prompt);

        if ($prompt->shot_id !== null || $project?->is_active !== true || $project->owner === null) {
            return;
        }

        $operation = $this->upscale->run($prompt, $project->owner, new RequestVideoUpscaleData(UpscaleExport::FULL_HD));

        $project->channels->filter(fn(Channel $channel): bool => $channel->type !== ChannelType::TIKTOK && $channel->owner_id === $project->owner_id && $channel->canReceiveProduction())->each(function (Channel $channel) use ($prompt, $operation): void {
            $request = $this->requests->forShort($prompt, $channel, $operation->initiator_id, operationId: $operation->id);

            if ($request->wasRecentlyCreated) {
                PublishEpisodeJob::dispatch($request->id)->afterCommit();
            }
        });
    }
}
