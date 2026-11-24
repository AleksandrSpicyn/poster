<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Episodes\EpisodePublishingContextData;
use App\Data\Publications\PublicationClaimData;
use App\Data\Publications\PublicationResolutionData;
use App\Data\Tiktok\TiktokPublicationData;
use App\Enums\ContentAudience;
use App\Enums\PublicationStatus;
use App\Models\Channel;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Models\SeriesAssembly;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** @extends EloquentRepository<PublicationRequest> */
readonly class PublicationRequestRepository extends EloquentRepository
{
    protected function model(): string
    {
        return PublicationRequest::class;
    }

    /** @return Collection<int, PublicationRequest> */
    public function expireClaims(int $limit): Collection
    {
        $expiredIds = PublicationRequest::query()->whereIn('status', [PublicationStatus::SUBMITTING, PublicationStatus::PUBLISHING_CONTAINER])
            ->where('submitted_at', '<', now()->subMinutes(10))
            ->orderBy('id')->limit($limit)->pluck('id');

        PublicationRequest::query()->whereKey($expiredIds)
            ->whereIn('status', [PublicationStatus::SUBMITTING, PublicationStatus::PUBLISHING_CONTAINER])
            ->where('submitted_at', '<', now()->subMinutes(10))
            ->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN, 'error_message' => 'A worker stopped before confirming the external result. Inspect its receipt before retrying.']);

        return PublicationRequest::query()->whereKey($expiredIds)->with('channel')->get();
    }

    /** @return Collection<int, PublicationRequest> */
    public function nextReconciliationBatch(int $limit): Collection
    {
        return DB::transaction(function () use ($limit): Collection {
            $batch = PublicationRequest::query()->where(function (Builder $query): void {
                $query->where('status', PublicationStatus::PLANNED)
                    ->where(fn(Builder $slot) => $slot->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
                    ->orWhere(fn(Builder $processing) => $processing->whereIn('status', [PublicationStatus::PROCESSING, PublicationStatus::INBOX])
                        ->whereNotNull('container_id')->where('submitted_at', '<=', now()->subMinutes(5)));
            })->oldest('updated_at')->orderBy('id')->limit($limit)->lockForUpdate()->get();

            if ($batch->isNotEmpty()) {
                PublicationRequest::query()->whereKey($batch->modelKeys())->touch();
            }

            return $batch;
        }, 3);
    }

    public function transitionClaim(PublicationRequest $request, PublicationStatus $expected, PublicationStatus $status, ?string $containerId, ?string $error): int
    {
        return PublicationRequest::query()->whereKey($request->id)
            ->where('status', $expected)
            ->update([
                'status' => $status,
                ...($containerId === null ? [] : ['container_id' => $containerId]),
                'error_message' => $error,
            ]);
    }

    public function failPendingProduction(ProductionRun $run): int
    {
        return PublicationRequest::query()->where('production_run_id', $run->id)
            ->whereIn('status', [PublicationStatus::PLANNED, PublicationStatus::PROCESSING])
            ->update(['status' => PublicationStatus::FAILED, 'error_message' => 'Automatic production stopped before publication.']);
    }

    /** Project и Series блокируются до Run и Request; вызывающий Task удерживает транзакцию. */
    public function lockClaimContext(PublicationRequest $request): ?PublicationClaimData
    {
        if (($request->episode_assembly_id === null) === ($request->series_assembly_id === null)) {
            throw new RuntimeException('A publication must have exactly one source.');
        }

        $relation = $request->series_assembly_id !== null ? 'series.assemblies' : 'series.episodes.revisions.assemblies';
        $assemblyId = $request->series_assembly_id ?? $request->episode_assembly_id;
        $project = Project::query()
            ->whereHas($relation, static fn(Builder $query): Builder => $query->whereKey($assemblyId))
            ->lockForUpdate()
            ->firstOrFail();

        if ($request->series_assembly_id !== null) {
            $assembly = SeriesAssembly::query()->findOrFail($request->series_assembly_id);
            $seriesId = $assembly->series_id;
            $episode = null;
        } else {
            $assembly = EpisodeAssembly::query()->findOrFail($request->episode_assembly_id);
            $revision = EpisodeRevision::query()->findOrFail($assembly->episode_revision_id);
            $episode = Episode::query()->findOrFail($revision->episode_id);
            $seriesId = $episode->series_id;
        }

        $series = Series::query()->lockForUpdate()->findOrFail($seriesId);
        $productionRun = $request->production_run_id === null ? null : ProductionRun::query()->lockForUpdate()->find($request->production_run_id);
        $current = $this->findLockedOrFail($request->id);

        if ($current->series_assembly_id !== $request->series_assembly_id || $current->episode_assembly_id !== $request->episode_assembly_id
            || $current->production_run_id !== $request->production_run_id) {
            return null;
        }

        return new PublicationClaimData($assembly, $episode, $series, $project, $productionRun, $current, User::query()->find($current->created_by));
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

        if ($request->prompt_id !== null) {
            $short = $request->prompt()->firstOrFail();

            return new PublicationResolutionData($request, $channel, $short->project()->firstOrFail(), $short);
        }

        $projectQuery = Project::query()->select('projects.*')->join('series', 'series.project_id', '=', 'projects.id');

        if ($request->series_assembly_id !== null) {
            $assembly = $request->seriesAssembly()->firstOrFail();
            $projectQuery->where('series.id', $assembly->series_id);
        } else {
            $assembly = $request->assembly()->firstOrFail();
            $projectQuery->join('episodes', 'episodes.series_id', '=', 'series.id')
                ->join('episode_revisions', 'episode_revisions.episode_id', '=', 'episodes.id')
                ->where('episode_revisions.id', $assembly->episode_revision_id);
        }

        return new PublicationResolutionData($request, $channel, $projectQuery->firstOrFail(), assemblyNumber: $assembly->number);
    }

    /** @return Collection<int, PublicationRequest> */
    public function recentForOwner(User $owner): Collection
    {
        return PublicationRequest::query()->where('created_by', $owner->id)->whereRelation('channel', 'owner_id', $owner->id)
            ->with('channel')->latest('id')->limit(20)->get();
    }

    public function shortWithProject(PublicationRequest $request): ?Prompt
    {
        return $request->prompt()->with('project')->first();
    }

    public function forShort(Prompt $prompt, Channel $channel, int $ownerId, ?TiktokPublicationData $tiktok = null, ?int $operationId = null): PublicationRequest
    {
        return PublicationRequest::query()->firstOrCreate(['prompt_id' => $prompt->id, 'channel_id' => $channel->id], [
            'created_by' => $ownerId,
            'status' => PublicationStatus::PLANNED,
            'payload' => [
                ...($operationId === null ? [] : ['export_operation_id' => $operationId]),
                'title' => $tiktok->title ?? ($operationId === null ? Str::limit($prompt->prompt, 97) : 'What if the animal ends up in another country #Shorts #AIArt #WhatIf'),
                'description' => $operationId === null ? '' : 'Generated by AI',
                'made_for_kids' => false,
                ...($tiktok instanceof TiktokPublicationData ? ['tiktok' => $tiktok->toArray()] : []),
            ],
        ]);
    }

    /**
     * @param Collection<int, Channel> $channels
     * @return Collection<int, PublicationRequest>
     */
    public function forFinalAssembly(SeriesAssembly $assembly, Collection $channels, User $actor, ?ProductionRun $run, ?TiktokPublicationData $tiktok = null): Collection
    {
        $releaseKey = 'series:' . $assembly->series_id . ':single_video';
        $existing = PublicationRequest::query()->where('release_key', $releaseKey)->whereIn('channel_id', $channels->modelKeys())
            ->lockForUpdate()->get()->keyBy('channel_id');
        $requests = new Collection();

        foreach ($channels as $channel) {
            $request = $existing->get($channel->id) ?? new PublicationRequest([
                'release_key' => $releaseKey,
                'channel_id' => $channel->id,
                'series_assembly_id' => $assembly->id,
                'created_by' => $actor->id,
                'production_run_id' => $run?->id,
                'status' => PublicationStatus::PLANNED,
                'payload' => [
                    ...($tiktok instanceof TiktokPublicationData ? ['tiktok' => $tiktok->toArray()] : []),
                    'title' => Str::limit($assembly->series->name, 97),
                    'description' => $assembly->series->specification['premise'],
                    'made_for_kids' => ($assembly->series->specification['audience'] ?? null) === ContentAudience::CHILDREN->value,
                ],
            ]);
            $request->setRelation('channel', $channel);

            if (! $request->exists) {
                $request->save();
            }

            if ($request->series_assembly_id !== $assembly->id) {
                if ($assembly->series->release_started_at === null && $request->status === PublicationStatus::PLANNED && $request->submitted_at === null) {
                    $request->update(['series_assembly_id' => $assembly->id, 'production_run_id' => $run?->id]);
                } else {
                    throw ValidationException::withMessages(['publication' => 'This cycle is already assigned to another final file.']);
                }
            }

            if ($run instanceof ProductionRun && $request->production_run_id !== $run->id && $request->status === PublicationStatus::FAILED
                && $request->error_message === 'Automatic production stopped before publication.') {
                if ($request->container_id !== null || $request->submitted_at === null) {
                    $request->update([
                        'production_run_id' => $run->id,
                        'status' => $request->container_id === null ? PublicationStatus::PLANNED : PublicationStatus::PROCESSING,
                        'error_message' => null,
                    ]);
                }
            }

            $requests->push($request);
        }

        return $requests;
    }

    public function reservedEpisodeSlot(EpisodeAssembly $assembly): ?Carbon
    {
        return PublicationRequest::query()->where('episode_assembly_id', $assembly->id)->whereNotNull('scheduled_at')
            ->lockForUpdate()->first()?->scheduled_at;
    }

    public function latestScheduledEpisodeSlot(Series $series): ?Carbon
    {
        return PublicationRequest::query()->whereHas('assembly.episodeRevision.episode', fn($episode) => $episode->where('series_id', $series->id))
            ->where('scheduled_at', '>=', now())->orderByDesc('scheduled_at')->lockForUpdate()->first()?->scheduled_at;
    }

    /**
     * @param Collection<int, Channel> $channels
     * @return Collection<int, PublicationRequest>
     */
    public function forAcceptedAssembly(EpisodeAssembly $assembly, Collection $channels, User $actor, ?ProductionRun $productionRun, EpisodePublishingContextData $context, Carbon $scheduledAt, string $description, ?TiktokPublicationData $tiktok = null, ?int $operationId = null): Collection
    {
        $existing = PublicationRequest::query()->where('episode_assembly_id', $assembly->id)->whereIn('channel_id', $channels->modelKeys())
            ->lockForUpdate()->get()->keyBy('channel_id');
        $requests = new Collection();

        foreach ($channels as $channel) {
            $request = $existing->get($channel->id) ?? new PublicationRequest([
                'episode_assembly_id' => $assembly->id,
                'channel_id' => $channel->id,
                'created_by' => $actor->id,
                'production_run_id' => $productionRun?->id,
                'scheduled_at' => $scheduledAt,
                'status' => PublicationStatus::PLANNED,
                'payload' => [
                    ...($operationId === null ? [] : ['export_operation_id' => $operationId]),
                    ...($tiktok instanceof TiktokPublicationData ? ['tiktok' => $tiktok->toArray()] : []),
                    'title' => Str::limit($context->revision->title, 97),
                    'description' => $description,
                    'use_public_description' => true,
                    'made_for_kids' => ($context->series->specification['audience'] ?? null) === ContentAudience::CHILDREN->value,
                ],
            ]);
            $request->setRelation('channel', $channel);

            if (! $request->exists) {
                $request->save();
            }

            $requests->push($request);
        }

        return $requests;
    }
}
