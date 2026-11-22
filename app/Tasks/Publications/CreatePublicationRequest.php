<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Data\Episodes\PublishEpisodeData;
use App\Data\Tiktok\TiktokPublicationData;
use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\SeriesOutputMode;
use App\Enums\TiktokDeliveryMode;
use App\Models\EpisodeAssembly;
use App\Models\ProductionRun;
use App\Models\PublicationRequest;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\ProductionRunRepository;
use App\Repositories\PublicationRequestRepository;
use App\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

class CreatePublicationRequest
{
    public function __construct(
        private readonly EpisodeRepository $episodes,
        private readonly ProductionRunRepository $runs,
        private readonly EpisodeAssemblyRepository $assemblies,
        private readonly UserRepository $users,
        private readonly ChannelRepository $channels,
        private readonly PublicationRequestRepository $publications,
    ) {}

    /** @return Collection<int, PublicationRequest> */
    public function run(EpisodeAssembly $assembly, PublishEpisodeData $data, User $actor, ?ProductionRun $productionRun = null, ?TiktokPublicationData $tiktok = null): Collection
    {
        $actor = $this->users->fresh($actor);

        if (! $actor instanceof User) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries', $assembly);

        return DB::transaction(function () use ($assembly, $data, $actor, $productionRun, $tiktok): Collection {
            $context = $this->assemblies->lockPublishingContext($assembly);
            $revision = $context->revision;
            $episode = $context->episode;
            $series = $context->series;
            $project = $context->project;

            if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO) {
                throw ValidationException::withMessages(['assembly' => 'Publish only the accepted final video of this cycle.']);
            }

            if ($project->owner_id !== $actor->id || ! $project->is_active) {
                throw ValidationException::withMessages(['project' => 'Enable this project before publishing.']);
            }

            if ($productionRun instanceof ProductionRun) {
                $this->runs->findLockedOrFail($productionRun->id);
            }

            $this->episodes->refresh($episode);
            $this->assemblies->refresh($assembly);

            if (! $actor->hasVerifiedEmail() || $series->archived_at !== null || $assembly->status !== AssemblyStatus::ACCEPTED
                || $episode->accepted_assembly_id !== $assembly->id || $episode->validity !== EpisodeValidity::CURRENT) {
                throw ValidationException::withMessages(['assembly' => 'Only the current accepted episode can be published.']);
            }

            $channels = $this->channels->selectedPublishingChannels($series->project_id, $data, $tiktok?->mode === TiktokDeliveryMode::DIRECT);

            if ($channels->count() !== count($data->channel_ids)) {
                throw ValidationException::withMessages(['channel_ids' => 'Choose supported channels belonging to this project.']);
            }

            $scheduledAt = $this->publications->episodeReleaseTime($assembly, $series);

            $description = $data->caption instanceof Optional
                ? trim(($revision->public_caption ?? '') . "\n\n" . $series->name . ' · ' . $episode->number)
                : ($data->caption ?? '');

            return $this->publications->forAcceptedAssembly($assembly, $channels, $actor, $productionRun, $context, $scheduledAt, $description, $tiktok);
        });
    }
}
