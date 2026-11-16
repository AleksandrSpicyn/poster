<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\PublicationStatus;
use App\Models\ProductionRun;
use App\Models\PublicationRequest;
use App\Models\User;
use App\Repositories\EpisodeRepository;
use App\Repositories\PublicationRequestRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClaimPublication
{
    public function __construct(private readonly PublicationRequestRepository $publications, private readonly EpisodeRepository $episodes) {}

    public function run(PublicationRequest $request, bool $container = false): bool
    {
        return DB::transaction(function () use ($request, $container): bool {
            $context = $this->publications->lockClaimContext($request);
            $assembly = $context->assembly;
            $episode = $context->episode;
            $series = $context->series;
            $project = $context->project;
            $productionRun = $context->productionRun;
            $request = $context->request;
            $user = $context->user;
            $userAllowed = $user instanceof User && Gate::forUser($user)->allows('manageSeries');
            $expected = $container ? PublicationStatus::PROCESSING : PublicationStatus::PLANNED;

            if ($request->status !== $expected) {
                return false;
            }

            if (! $project->is_active) {
                $this->publications->update($request, ['status' => PublicationStatus::FAILED, 'error_message' => 'The project is disabled.']);

                return false;
            }

            if ($request->production_run_id !== null && (! $productionRun instanceof ProductionRun || ! $productionRun->status->isActive()
                || ! $series->automation_enabled || $productionRun->series_id !== $series->id)) {
                $this->publications->update($request, ['status' => PublicationStatus::FAILED, 'error_message' => 'Automatic production stopped before publication.']);

                return false;
            }

            $this->episodes->refresh($episode);

            if (! $userAllowed || $series->archived_at !== null || $assembly->status !== AssemblyStatus::ACCEPTED
                || $episode->validity !== EpisodeValidity::CURRENT || $episode->accepted_assembly_id !== $assembly->id) {
                $this->publications->update($request, ['status' => PublicationStatus::FAILED, 'error_message' => 'The accepted episode is no longer current or publishing permission was revoked.']);

                return false;
            }

            $this->publications->update($request, ['status' => $container ? PublicationStatus::PUBLISHING_CONTAINER : PublicationStatus::SUBMITTING, 'submitted_at' => now()]);

            return true;
        });
    }
}
