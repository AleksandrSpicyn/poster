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
use App\Models\SeriesAssembly;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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
                ->where(fn($slot) => $slot->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
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
        return PublicationRequest::query()->where('production_run_id', $run->id)
            ->whereIn('status', [PublicationStatus::PLANNED, PublicationStatus::PROCESSING])
            ->update(['status' => PublicationStatus::FAILED, 'error_message' => 'Automatic production stopped before publication.']);
    }

    /** Project и Series блокируются до Run и Request; вызывающий Task удерживает транзакцию. */
    public function lockClaimContext(PublicationRequest $request): PublicationClaimData
    {
        if (($request->episode_assembly_id === null) === ($request->series_assembly_id === null)) {
            throw new RuntimeException('A publication must have exactly one source.');
        }

        if ($request->series_assembly_id !== null) {
            $assembly = SeriesAssembly::query()->findOrFail($request->series_assembly_id);
            $series = Series::query()->findOrFail($assembly->series_id);
            $project = Project::query()->lockForUpdate()->findOrFail($series->project_id);
            $series = Series::query()->lockForUpdate()->findOrFail($series->id);
            $productionRun = $request->production_run_id === null ? null : ProductionRun::query()->lockForUpdate()->find($request->production_run_id);
            $request = PublicationRequest::query()->lockForUpdate()->findOrFail($request->id);

            return new PublicationClaimData($assembly, null, $series, $project, $productionRun, $request, User::query()->find($request->created_by));
        }

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
        $assembly = $request->series_assembly_id === null ? $request->assembly()->firstOrFail() : $request->seriesAssembly()->firstOrFail();
        $series = $assembly instanceof SeriesAssembly ? $assembly->series()->firstOrFail() : $assembly->episodeRevision()->firstOrFail()->episode()->firstOrFail()->series()->firstOrFail();

        return new PublicationResolutionData($request, $channel, $assembly, $series);
    }

    public function forFinalAssembly(SeriesAssembly $assembly, Channel $channel, User $actor, ?ProductionRun $run): PublicationRequest
    {
        $request = PublicationRequest::query()->firstOrCreate(
            ['release_key' => 'series:' . $assembly->series_id . ':single_video', 'channel_id' => $channel->id],
            ['series_assembly_id' => $assembly->id, 'created_by' => $actor->id, 'production_run_id' => $run?->id,
                'status' => PublicationStatus::PLANNED, 'payload' => ['title' => Str::limit($assembly->series->name, 97),
                    'description' => $assembly->series->specification['premise'],
                    'made_for_kids' => ($assembly->series->specification['audience'] ?? null) === ContentAudience::CHILDREN->value]],
        );

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
                $request->update(['production_run_id' => $run->id, 'status' => $request->container_id === null ? PublicationStatus::PLANNED : PublicationStatus::PROCESSING, 'error_message' => null]);
            }
        }

        return $request;
    }

    /** The caller holds the series lock while reserving a shared slot for every selected channel. */
    public function episodeReleaseTime(EpisodeAssembly $assembly, Series $series, bool $nextSlot): ?Carbon
    {
        $reserved = PublicationRequest::query()->where('episode_assembly_id', $assembly->id)->whereNotNull('scheduled_at')->lockForUpdate()->first();

        if ($reserved?->scheduled_at !== null) {
            return $reserved->scheduled_at;
        }

        if (! $nextSlot) {
            return null;
        }

        $last = PublicationRequest::query()->whereHas('assembly.episodeRevision.episode', fn($episode) => $episode->where('series_id', $series->id))
            ->where('scheduled_at', '>=', now())->orderByDesc('scheduled_at')->lockForUpdate()->first();
        $after = ($last->scheduled_at ?? now())->copy()->setTimezone('Europe/Moscow');
        $slot = $after->copy()->setTime(19, 0);

        while ($slot->lte($after) || ! in_array($slot->dayOfWeekIso, [2, 5], true)) {
            $slot->addDay();
        }

        return $slot->setTimezone(config()->string('app.timezone'));
    }

    public function forAcceptedAssembly(EpisodeAssembly $assembly, Channel $channel, User $actor, ?ProductionRun $productionRun, EpisodePublishingContextData $context, ?Carbon $scheduledAt = null): PublicationRequest
    {
        return PublicationRequest::query()->firstOrCreate(
            ['episode_assembly_id' => $assembly->id, 'channel_id' => $channel->id],
            ['created_by' => $actor->id, 'production_run_id' => $productionRun?->id, 'scheduled_at' => $scheduledAt, 'status' => PublicationStatus::PLANNED, 'payload' => [
                'title' => Str::limit($context->revision->title, 97),
                'description' => trim(($context->revision->public_caption ?? '') . "\n\n" . $context->series->name . ' · ' . $context->episode->number),
                'use_public_description' => true,
                'made_for_kids' => ($context->series->specification['audience'] ?? null) === ContentAudience::CHILDREN->value,
            ]],
        );
    }
}
