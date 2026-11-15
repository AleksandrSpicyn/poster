<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\PublicationStatus;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClaimPublication
{
    public function run(PublicationRequest $request, bool $container = false): bool
    {
        return DB::transaction(function () use ($request, $container): bool {
            $assembly = EpisodeAssembly::query()->findOrFail($request->episode_assembly_id);
            $revision = EpisodeRevision::query()->findOrFail($assembly->episode_revision_id);
            $episode = Episode::query()->findOrFail($revision->episode_id);
            $series = Series::query()->lockForUpdate()->findOrFail($episode->series_id);
            $request = PublicationRequest::query()->lockForUpdate()->findOrFail($request->id);
            $actor = $request->created_by;
            $user = User::query()->find($actor);
            $userAllowed = $user !== null && Gate::forUser($user)->allows('manageSeries');
            $expected = $container ? PublicationStatus::PROCESSING : PublicationStatus::PLANNED;

            if ($request->status !== $expected) {
                return false;
            }

            $episode->refresh();

            if (! $userAllowed || $series->archived_at !== null || $assembly->status !== AssemblyStatus::ACCEPTED
                || $episode->validity !== EpisodeValidity::CURRENT || $episode->accepted_assembly_id !== $assembly->id) {
                $request->update(['status' => PublicationStatus::FAILED, 'error_message' => 'The accepted episode is no longer current or publishing permission was revoked.']);

                return false;
            }

            $request->update(['status' => $container ? PublicationStatus::PUBLISHING_CONTAINER : PublicationStatus::SUBMITTING, 'submitted_at' => now()]);

            return true;
        });
    }
}
