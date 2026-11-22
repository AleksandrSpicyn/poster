<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Data\Episodes\PublishEpisodeData;
use App\Data\Tiktok\TiktokPublicationData;
use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Enums\TiktokDeliveryMode;
use App\Models\ProductionRun;
use App\Models\PublicationRequest;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\PublicationRequestRepository;
use App\Repositories\SeriesAssemblyRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class CreateSeriesPublication
{
    public function __construct(private ProjectRepository $projects, private SeriesRepository $series, private SeriesAssemblyRepository $assemblies, private ChannelRepository $channels, private PublicationRequestRepository $publications) {}

    /** @return Collection<int, PublicationRequest> */
    public function run(SeriesAssembly $assembly, PublishEpisodeData $data, User $actor, ?ProductionRun $run = null, ?TiktokPublicationData $tiktok = null): Collection
    {
        Gate::forUser($actor)->authorize('manageSeries');

        return DB::transaction(function () use ($assembly, $data, $actor, $run, $tiktok): Collection {
            $assembly = $this->assemblies->withContext($assembly->id);
            $project = $this->projects->findLockedOrFail($assembly->series->project_id);
            $series = $this->series->findLockedOrFail($assembly->series_id);

            if ($project->owner_id !== $actor->id || ! $project->is_active || ! $actor->hasVerifiedEmail() || $series->archived_at !== null || $series->cycle_paused_at !== null
                || $assembly->status !== AssemblyStatus::ACCEPTED || $series->final_assembly_id !== $assembly->id || ! $this->assemblies->isCurrent($assembly)) {
                throw ValidationException::withMessages(['assembly' => 'Accept the current complete video before publishing.']);
            }

            $pinned = $this->series->cycleChannelIds($series);

            if (array_diff($data->channel_ids, $pinned) !== []) {
                throw ValidationException::withMessages(['channel_ids' => 'Choose channels from the pinned cycle delivery plan.']);
            }

            $channels = $this->channels->selectedPublishingChannels($series->project_id, $data, $tiktok?->mode === TiktokDeliveryMode::DIRECT);

            if ($channels->count() !== count($data->channel_ids)) {
                throw ValidationException::withMessages(['channel_ids' => 'A selected channel is disabled or disconnected.']);
            }

            $media = $assembly->getFirstMedia(SeriesAssembly::VIDEO);

            if (! $media instanceof Media || $assembly->duration_ms === null || $assembly->duration_ms > 600000
                || $channels->contains(fn($channel): bool => $channel->type === ChannelType::INSTAGRAM && filesize($media->getPath()) > 300000000)) {
                throw ValidationException::withMessages(['assembly' => 'The final file exceeds the supported publishing profile (600 seconds; Instagram 300 MB).']);
            }

            $requests = new Collection();

            foreach ($channels as $channel) {
                $requests->push($this->publications->forFinalAssembly($assembly, $channel, $actor, $run, $tiktok));
            }

            return $requests;
        });
    }
}
