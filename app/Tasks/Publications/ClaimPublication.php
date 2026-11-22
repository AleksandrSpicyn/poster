<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Data\Publications\PublicationClaimData;
use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\PublicationStatus;
use App\Enums\SeriesOutputMode;
use App\Models\Episode;
use App\Models\ProductionRun;
use App\Models\PublicationRequest;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\PublicationRequestRepository;
use App\Repositories\SeriesAssemblyRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClaimPublication
{
    public function __construct(private readonly PublicationRequestRepository $publications, private readonly EpisodeRepository $episodes, private readonly ChannelRepository $channels, private readonly SeriesRepository $seriesRepository, private readonly SeriesAssemblyRepository $finalAssemblies) {}

    public function run(PublicationRequest $request, bool $container = false): bool
    {
        return DB::transaction(function () use ($request, $container): bool {
            $context = $request->prompt_id === null ? $this->publications->lockClaimContext($request) : null;
            $request = $context->request ?? $this->publications->findLockedOrFail($request->id);
            $expected = $container ? PublicationStatus::PROCESSING : PublicationStatus::PLANNED;

            if (($request->status !== $expected && (! $container || $request->status !== PublicationStatus::INBOX)) || (! $container && $request->scheduled_at?->isFuture())) {
                return false;
            }

            $error = $context instanceof PublicationClaimData ? $this->rejectionReason($context) : $this->shortRejectionReason($request);

            if ($error !== null) {
                $this->publications->update($request, ['status' => PublicationStatus::FAILED, 'error_message' => $error]);

                return false;
            }

            if ($context?->assembly instanceof SeriesAssembly && $context->series->release_started_at === null) {
                $this->seriesRepository->update($context->series, ['release_started_at' => now()]);
            }

            $this->publications->update($request, ['status' => $container ? PublicationStatus::PUBLISHING_CONTAINER : PublicationStatus::SUBMITTING, 'submitted_at' => now()]);

            return true;
        });
    }

    private function shortRejectionReason(PublicationRequest $request): ?string
    {
        $short = $request->prompt()->with('project')->first();

        if ($short === null || ! $short->generated || $short->shot_id !== null || $short->project?->is_active !== true
            || $short->project->owner_id !== $request->created_by || ! User::query()->whereKey($request->created_by)->whereNotNull('email_verified_at')->exists()
            || ! $this->channels->readyForProject($request->channel_id, $short->project_id)) {
            return 'The short or its channel is no longer available to this owner.';
        }

        return null;
    }

    private function rejectionReason(PublicationClaimData $context): ?string
    {
        $assembly = $context->assembly;
        $episode = $context->episode;
        $series = $context->series;
        $project = $context->project;
        $productionRun = $context->productionRun;
        $request = $context->request;
        $user = $context->user;
        $userAllowed = $user instanceof User && $user->id === $project->owner_id && Gate::forUser($user)->allows('manageSeries');

        if (! $project->is_active) {
            return 'The project is disabled.';
        }

        if (! $this->channels->readyForProject($request->channel_id, $project->id)) {
            return 'The publishing channel is disabled, disconnected or no longer attached to this project.';
        }

        if ($request->production_run_id !== null && (! $productionRun instanceof ProductionRun || ! $productionRun->status->isActive()
            || ! $series->automation_enabled || $productionRun->series_id !== $series->id)) {
            return 'Automatic production stopped before publication.';
        }

        if ($assembly instanceof SeriesAssembly) {
            if (! $userAllowed || $series->output_mode !== SeriesOutputMode::SINGLE_VIDEO || $series->archived_at !== null || $series->cycle_paused_at !== null
                || $assembly->status !== AssemblyStatus::ACCEPTED || $series->final_assembly_id !== $assembly->id || ! $this->finalAssemblies->isCurrent($assembly)) {
                return 'The accepted final video is no longer current.';
            }

            return null;
        }

        if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO || ! $episode instanceof Episode) {
            return 'Intermediate parts cannot be published.';
        }

        $this->episodes->refresh($episode);

        if (! $userAllowed || $series->archived_at !== null || $assembly->status !== AssemblyStatus::ACCEPTED
            || $episode->validity !== EpisodeValidity::CURRENT || $episode->accepted_assembly_id !== $assembly->id) {
            return 'The accepted episode is no longer current or publishing permission was revoked.';
        }

        return null;
    }
}
