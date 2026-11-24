<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Story\Snapshot;
use App\Data\Episodes\EpisodeAssemblyPlanData;
use App\Data\Upscale\UpscaleCapabilitiesData;
use App\Data\Upscale\UpscaleReceiptData;
use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\RevisionStatus;
use App\Enums\SeriesOutputMode;
use App\Enums\UpscaleExport;
use App\Enums\UpscaleOperationStatus;
use App\Enums\UpscaleProvider;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\Series;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Models\VideoUpscaleOperation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @extends EloquentRepository<VideoUpscaleOperation> */
readonly class VideoUpscaleOperationRepository extends EloquentRepository
{
    public function __construct(private EpisodeAssemblyRepository $assemblies, private UserRepository $users, private SeriesAssemblyRepository $finals) {}

    /** @return list<int> */
    public function due(): array
    {
        /** @var list<int> $ids */
        $ids = $this->query()->whereIn('status', [
            UpscaleOperationStatus::PENDING, UpscaleOperationStatus::SUBMITTING, UpscaleOperationStatus::SUBMITTED,
            UpscaleOperationStatus::PROCESSING, UpscaleOperationStatus::DOWNLOADING,
        ])->where('updated_at', '<', now()->subMinute())->orderBy('updated_at')->limit(100)->pluck('id')->all();

        return $ids;
    }

    public function forRelease(EpisodeAssembly|SeriesAssembly|Prompt $assembly): ?VideoUpscaleOperation
    {
        return $assembly->upscales()->whereNull('parent_id')->where('profile_snapshot->export', UpscaleExport::FULL_HD->value)
            ->latest('id')->first();
    }

    public function storeReceipt(VideoUpscaleOperation $operation, UpscaleReceiptData $receipt): void
    {
        $operation->receipt = ['id' => $receipt->id, 'statusUrl' => $receipt->statusUrl, 'resultUrl' => $receipt->resultUrl];
        $operation->status = UpscaleOperationStatus::SUBMITTED;
        $operation->save();
    }

    /** @return Collection<int, VideoUpscaleOperation> */
    public function parts(VideoUpscaleOperation $operation): Collection
    {
        return $operation->parts()->get();
    }

    public function recordInput(VideoUpscaleOperation $operation, Media $media, string $checksum): void
    {
        $operation->source_media_id = $media->id;
        $operation->source_sha256 = $checksum;
        $operation->save();
    }

    public function completeWithResult(VideoUpscaleOperation $operation, Media $media, string $checksum): void
    {
        $operation->result_media_id = $media->id;
        $operation->result_sha256 = $checksum;
        $operation->status = UpscaleOperationStatus::READY;
        $operation->save();
    }

    public function mark(VideoUpscaleOperation $operation, UpscaleOperationStatus $status, ?string $error = null): void
    {
        $operation->status = $status;
        $operation->setAttribute('last_error_at', $error === null ? null : now());
        $operation->setAttribute('last_error_class', $error);
        $operation->save();
    }

    public function claim(VideoUpscaleOperation $operation, EpisodeAssembly|SeriesAssembly|Prompt $assembly): bool
    {
        return DB::transaction(function () use ($operation, $assembly): bool {
            $this->users->findLockedOrFail($operation->initiator_id);
            $project = match (true) {
                $assembly instanceof EpisodeAssembly => $this->assemblies->lockPublishingContext($assembly)->project,
                $assembly instanceof SeriesAssembly => $assembly->series->project()->lockForUpdate()->firstOrFail(),
                default => $assembly->project()->lockForUpdate()->firstOrFail(),
            };
            $locked = $this->findLockedOrFail($operation->id);

            if ($locked->status !== UpscaleOperationStatus::PENDING) {
                return false;
            }

            $spent = $this->query()->where('initiator_id', $project->owner_id)
                ->whereNotIn('status', [UpscaleOperationStatus::PENDING, UpscaleOperationStatus::CANCELLED])
                ->selectRaw('COALESCE(SUM(COALESCE(actual_cost_e8, estimated_cost_e8)), 0) as spent')->value('spent');

            $limit = config()->integer('services.upscale.budget_e8');
            $assembly->refresh();

            if (! $this->eligible($assembly, $operation->initiator_id)
                || ($assembly instanceof EpisodeAssembly && ! isset($operation->source_snapshot['media_id']) && Snapshot::hash($assembly->manifest) !== Snapshot::hash($operation->source_snapshot))
                || ! is_numeric($spent) || ($limit !== -1 && (int) $spent + $operation->estimated_cost_e8 > $limit)) {
                $this->mark($locked, UpscaleOperationStatus::CANCELLED);

                return false;
            }

            $this->mark($locked, UpscaleOperationStatus::SUBMITTING);

            return true;
        }, 3);
    }

    private function eligible(EpisodeAssembly|SeriesAssembly|Prompt $assembly, int $ownerId): bool
    {
        if ($assembly instanceof Prompt) {
            return $assembly->shot_id === null && $assembly->generated && $assembly->project?->is_active === true
                && $assembly->project->owner_id === $ownerId && $this->users->isVerified($ownerId);
        }

        if ($assembly instanceof SeriesAssembly) {
            $series = $assembly->series;

            return $assembly->status === AssemblyStatus::ACCEPTED && $series->final_assembly_id === $assembly->id
                && $series->archived_at === null && $series->project?->is_active === true
                && $series->project->owner_id === $ownerId && $this->users->isVerified($ownerId) && $this->finals->isCurrent($assembly);
        }

        $revision = $assembly->episodeRevision;
        $episode = $revision?->episode;
        $series = $episode?->series;

        return $series instanceof Series
            && $series->output_mode === SeriesOutputMode::EPISODES && $series->archived_at === null
            && $series->project?->is_active === true && $series->project->owner_id === $ownerId
            && $this->users->isVerified($ownerId)
            && $episode->current_revision_id === $revision->id && $episode->validity === EpisodeValidity::CURRENT
            && $revision->status === RevisionStatus::APPROVED
            && ($assembly->status === AssemblyStatus::ACCEPTED
                ? $episode->accepted_assembly_id === $assembly->id
                : $assembly->status === AssemblyStatus::READY && $revision->base_continuity_version === $series->continuity_version
                    && data_get($revision->context_snapshot, 'specification_version') === $series->specification_version);
    }

    protected function model(): string
    {
        return VideoUpscaleOperation::class;
    }

    /** @param array<string, mixed> $snapshot */
    public function plan(
        EpisodeAssembly|SeriesAssembly|Prompt $assembly,
        User $initiator,
        UpscaleProvider $provider,
        UpscaleCapabilitiesData $capabilities,
        UpscaleExport $export,
        int $estimatedCostE8,
        array $snapshot,
        ?int $retryOperationId = null,
        ?int $parentId = null,
    ): VideoUpscaleOperation {
        return DB::transaction(function () use ($assembly, $initiator, $provider, $capabilities, $export, $estimatedCostE8, $snapshot, $retryOperationId, $parentId): VideoUpscaleOperation {
            $locked = $assembly->newQuery()->lockForUpdate()->findOrFail($assembly->id);

            if (! $this->eligible($locked, $initiator->id)) {
                throw ValidationException::withMessages(['assembly' => 'Select a current assembly owned by the active project.']);
            }

            $duration = $snapshot['duration_ms'] ?? EpisodeAssemblyPlanData::fromManifest($snapshot)->durationMs;

            if (! is_int($duration) || $duration < 1 || ($duration > $capabilities->maxDurationMs && ! isset($snapshot['part_count'])) || $estimatedCostE8 < 0) {
                throw ValidationException::withMessages(['assembly' => 'Unsupported duration or cost estimate.']);
            }

            $profile = [
                'version' => 1, 'fit' => 'contain', 'model' => $capabilities->model,
                'source_width' => $capabilities->sourceWidth, 'source_height' => $capabilities->sourceHeight,
                'fps' => $capabilities->fps, 'export' => $export->value,
            ];
            $key = Snapshot::hash([
                'owner' => $initiator->id, 'assembly' => $locked->id,
                'manifest_hash' => $locked instanceof Prompt ? null : $locked->manifest_hash, 'manifest' => $snapshot,
                ...($locked instanceof EpisodeAssembly ? [] : ['source_type' => $locked->getMorphClass()]),
                'provider' => $provider->value, 'profile' => $profile,
                ...($parentId === null ? [] : ['parent' => $parentId]),
            ]);
            $latest = $this->query()->where('operation_key', $key)->orderByDesc('attempt')->lockForUpdate()->first();

            if ($latest !== null && ($latest->id !== $retryOperationId || ! in_array($latest->status, [UpscaleOperationStatus::FAILED, UpscaleOperationStatus::CANCELLED], true))) {
                return $latest;
            }

            if ($latest === null && $retryOperationId !== null) {
                throw ValidationException::withMessages(['attempt' => 'The selected attempt belongs to another export.']);
            }

            return $this->query()->create([
                $locked->getForeignKey() => $locked->id, 'parent_id' => $parentId, 'initiator_id' => $initiator->id,
                'operation_key' => $key, 'attempt' => ($latest->attempt ?? 0) + 1,
                'provider' => $provider, 'status' => UpscaleOperationStatus::PENDING,
                'source_snapshot' => $snapshot, 'profile_snapshot' => $profile,
                'estimated_cost_e8' => $estimatedCostE8, 'currency' => 'USD',
            ]);
        });
    }
}
