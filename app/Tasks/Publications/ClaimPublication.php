<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Actions\Upscale\RequestVideoUpscaleAction;
use App\Data\Publications\PublicationClaimData;
use App\Data\Upscale\RequestVideoUpscaleData;
use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Enums\EpisodeValidity;
use App\Enums\PublicationStatus;
use App\Enums\SeriesOutputMode;
use App\Enums\UpscaleExport;
use App\Enums\UpscaleOperationStatus;
use App\Models\Episode;
use App\Models\ProductionRun;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PublicationRequestRepository;
use App\Repositories\SeriesAssemblyRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\UserRepository;
use App\Repositories\VideoUpscaleOperationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ClaimPublication
{
    public function __construct(private readonly RequestVideoUpscaleAction $prepareExport, private readonly VideoUpscaleOperationRepository $upscales, private readonly MediaRepository $media, private readonly PublicationRequestRepository $publications, private readonly EpisodeRepository $episodes, private readonly ChannelRepository $channels, private readonly SeriesRepository $seriesRepository, private readonly SeriesAssemblyRepository $finalAssemblies, private readonly UserRepository $users) {}

    public function run(PublicationRequest $request, bool $container = false): bool
    {
        return DB::transaction(function () use ($request, $container): bool {
            $context = $request->prompt_id === null ? $this->publications->lockClaimContext($request) : null;

            if ($request->prompt_id === null && ! $context instanceof PublicationClaimData) {
                return false;
            }

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

            if (! $container) {
                $source = $context->assembly ?? $this->publications->shortWithProject($request);

                if ($source === null) {
                    $this->publications->update($request, ['status' => PublicationStatus::FAILED, 'error_message' => 'Исходный ролик недоступен.']);

                    return false;
                }

                $owner = $context->user ?? $this->users->findOrFail($request->created_by);
                $operation = isset($request->payload['media_id'], $request->payload['export_operation_id'])
                    ? $this->upscales->findOrFail($request->payload['export_operation_id'])
                    : ($this->upscales->forRelease($source) ?? $this->prepareExport->run($source, $owner, new RequestVideoUpscaleData(UpscaleExport::FULL_HD)));

                if (($request->payload['export_operation_id'] ?? null) !== $operation->id) {
                    $this->publications->update($request, ['payload' => [...$request->payload, 'export_operation_id' => $operation->id]]);
                }

                if (in_array(
                    $operation->status,
                    [UpscaleOperationStatus::FAILED, UpscaleOperationStatus::CANCELLED, UpscaleOperationStatus::SUBMISSION_UNKNOWN],
                    true,
                )) {
                    $this->publications->update($request, ['status' => PublicationStatus::FAILED, 'error_message' => 'Не удалось подготовить файл выпуска.']);

                    return false;
                }

                if ($operation->status !== UpscaleOperationStatus::READY || $operation->result_media_id === null || $operation->result_sha256 === null) {
                    return false;
                }

                $media = $this->media->episodeExport($source, $operation->result_media_id, $operation->result_sha256);

                if (! $media instanceof Media || ($context?->assembly instanceof SeriesAssembly
                    && $this->channels->forPublication($request)->type === ChannelType::INSTAGRAM && filesize($media->getPath()) > 300000000)) {
                    $this->publications->update($request, ['status' => PublicationStatus::FAILED, 'error_message' => 'Файл выпуска недоступен.']);

                    return false;
                }

                $request->payload = [...$request->payload, 'media_id' => $operation->result_media_id, 'sha256' => $operation->result_sha256];
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
        $short = $this->publications->shortWithProject($request);

        if (! $short instanceof Prompt || ! $short->generated || $short->shot_id !== null || $short->project?->is_active !== true
            || $short->project->owner_id !== $request->created_by || ! $this->users->isVerified($request->created_by)
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

        if (! $this->channels->readyForProject($request->channel_id, $project->id, production: $productionRun instanceof ProductionRun)) {
            return 'The publishing channel is disabled, disconnected or no longer attached to this project.';
        }

        if ($request->production_run_id !== null && (! $productionRun instanceof ProductionRun || ! $productionRun->status->isActive()
            || (! $series->automation_enabled && ! $productionRun->wasStartedManually()) || $productionRun->series_id !== $series->id)) {
            return 'Automatic production stopped before publication.';
        }

        if ($productionRun instanceof ProductionRun && (! $productionRun->allowsAutomaticDelivery($series)
            || ($productionRun->deliveryChannelIds() !== null && ! in_array($request->channel_id, $productionRun->deliveryChannelIds(), true)))) {
            return 'Этот канал исключён из автоматической публикации.';
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

        if (isset($request->payload['media_id']) && ! $this->media->episodeExport($assembly, $request->payload['media_id'], $request->payload['sha256'] ?? '') instanceof Media) {
            return 'Выбранный файл публикации изменился или недоступен.';
        }

        $this->episodes->refresh($episode);

        if (! $userAllowed || $series->archived_at !== null || $assembly->status !== AssemblyStatus::ACCEPTED
            || $episode->validity !== EpisodeValidity::CURRENT || $episode->accepted_assembly_id !== $assembly->id) {
            return 'The accepted episode is no longer current or publishing permission was revoked.';
        }

        return null;
    }
}
