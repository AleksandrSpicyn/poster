<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Episodes\EpisodePublishingContextData;
use App\Data\Publications\PublicationClaimData;
use App\Data\Publications\PublicationResolutionData;
use App\Enums\ContentAudience;
use App\Enums\PublicationStatus;
use App\Models\Channel;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/** @extends EloquentRepository<PublicationRequest> */
readonly class PublicationRequestRepository extends EloquentRepository
{
    protected function model(): string
    {
        return PublicationRequest::class;
    }

    public function expireClaims(int $limit): int
    {
        $expiredIds = PublicationRequest::query()->whereIn('status', [PublicationStatus::SUBMITTING, PublicationStatus::PUBLISHING_CONTAINER])
            ->where('submitted_at', '<', now()->subMinutes(10))
            ->orderBy('id')->limit($limit)->pluck('id');

        return PublicationRequest::query()->whereKey($expiredIds)
            ->whereIn('status', [PublicationStatus::SUBMITTING, PublicationStatus::PUBLISHING_CONTAINER])
            ->where('submitted_at', '<', now()->subMinutes(10))
            ->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN, 'error_message' => 'A worker stopped before confirming the external result. Inspect its receipt before retrying.']);
    }

    /** @return Collection<int, PublicationRequest> */
    public function pending(int $limit): Collection
    {
        return PublicationRequest::query()->where(function ($query): void {
            $query->where('status', PublicationStatus::PLANNED)
                ->orWhere(fn($processing) => $processing->where('status', PublicationStatus::PROCESSING)
                    ->whereNotNull('container_id')->where('submitted_at', '<=', now()->subMinutes(5)));
        })->orderBy('id')->limit($limit)->get();
    }

    public function updateUnpublished(PublicationRequest $request, PublicationStatus $status, ?string $containerId, ?string $error): int
    {
        return PublicationRequest::query()->whereKey($request->id)
            ->where('status', '!=', PublicationStatus::PUBLISHED)
            ->update(['status' => $status, 'container_id' => $containerId ?? $request->container_id, 'error_message' => $error]);
    }

    public function failPendingProduction(ProductionRun $run): int
    {
        return PublicationRequest::query()->where('episode_assembly_id', $run->episode_assembly_id)
            ->where('production_run_id', $run->id)
            ->whereIn('status', [PublicationStatus::PLANNED, PublicationStatus::PROCESSING])
            ->update(['status' => PublicationStatus::FAILED, 'error_message' => 'Automatic production stopped before publication.']);
    }

    /** Project и Series блокируются до Run и Request; вызывающий Task удерживает транзакцию. */
    public function lockClaimContext(PublicationRequest $request): PublicationClaimData
    {
        $assembly = EpisodeAssembly::query()->findOrFail($request->episode_assembly_id);
        $revision = EpisodeRevision::query()->findOrFail($assembly->episode_revision_id);
        $episode = Episode::query()->findOrFail($revision->episode_id);
        $series = Series::query()->findOrFail($episode->series_id);
        $project = Project::query()->lockForUpdate()->findOrFail($series->project_id);
        $series = Series::query()->lockForUpdate()->findOrFail($episode->series_id);
        $productionRun = $request->production_run_id !== null
            ? ProductionRun::query()->lockForUpdate()->find($request->production_run_id) : null;
        $request = PublicationRequest::query()->lockForUpdate()->findOrFail($request->id);
        $user = User::query()->find($request->created_by);

        return new PublicationClaimData($assembly, $episode, $series, $project, $productionRun, $request, $user);
    }

    public function resolutionContext(PublicationRequest $request, bool $lock): PublicationResolutionData
    {
        $query = PublicationRequest::query();

        if ($lock) {
            $query->lockForUpdate();
        }

        $request = $query->findOrFail($request->id);
        $channelQuery = $request->channel();

        if ($lock) {
            $channelQuery->lockForUpdate();
        }

        $channel = $channelQuery->firstOrFail();
        $assembly = $request->assembly()->firstOrFail();
        $series = $assembly->episodeRevision()->firstOrFail()->episode()->firstOrFail()->series()->firstOrFail();

        return new PublicationResolutionData($request, $channel, $assembly, $series);
    }

    public function forAcceptedAssembly(EpisodeAssembly $assembly, Channel $channel, User $actor, ?ProductionRun $productionRun, EpisodePublishingContextData $context): PublicationRequest
    {
        return PublicationRequest::query()->firstOrCreate(
            ['episode_assembly_id' => $assembly->id, 'channel_id' => $channel->id],
            ['created_by' => $actor->id, 'production_run_id' => $productionRun?->id, 'status' => PublicationStatus::PLANNED, 'payload' => [
                'title' => Str::limit($context->series->name . ' · ' . $context->episode->number . ' · ' . $context->revision->title, 97),
                'description' => $context->revision->objective,
                'made_for_kids' => ($context->series->specification['audience'] ?? null) === ContentAudience::CHILDREN->value,
            ]],
        );
    }
}
