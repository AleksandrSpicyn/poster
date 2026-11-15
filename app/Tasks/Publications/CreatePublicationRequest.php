<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Data\Episodes\PublishEpisodeData;
use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Enums\ContentAudience;
use App\Enums\EpisodeValidity;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePublicationRequest
{
    /** @return Collection<int, PublicationRequest> */
    public function run(EpisodeAssembly $assembly, PublishEpisodeData $data, User $actor, ?ProductionRun $productionRun = null): Collection
    {
        $actor = $actor->fresh();

        if ($actor === null) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries');

        return DB::transaction(function () use ($assembly, $data, $actor, $productionRun): Collection {
            $revision = EpisodeRevision::query()->findOrFail($assembly->episode_revision_id);
            $episode = Episode::query()->findOrFail($revision->episode_id);
            $series = Series::query()->findOrFail($episode->series_id);
            $project = Project::query()->lockForUpdate()->findOrFail($series->project_id);
            $series = Series::query()->lockForUpdate()->findOrFail($episode->series_id);

            if (! $project->is_active) {
                throw ValidationException::withMessages(['project' => 'Enable this project before publishing.']);
            }

            if ($productionRun instanceof ProductionRun) {
                ProductionRun::query()->lockForUpdate()->findOrFail($productionRun->id);
            }

            $episode->refresh();
            $assembly->refresh();

            if (! $actor->hasVerifiedEmail() || $series->archived_at !== null || $assembly->status !== AssemblyStatus::ACCEPTED
                || $episode->accepted_assembly_id !== $assembly->id || $episode->validity !== EpisodeValidity::CURRENT) {
                throw ValidationException::withMessages(['assembly' => 'Only the current accepted episode can be published.']);
            }

            $channels = Channel::query()->where('project_id', $series->project_id)
                ->whereIn('id', $data->channel_ids)
                ->whereIn('type', [ChannelType::YOUTUBE, ChannelType::INSTAGRAM])
                ->get();

            if ($channels->count() !== count($data->channel_ids)) {
                throw ValidationException::withMessages(['channel_ids' => 'Choose supported channels belonging to this project.']);
            }

            $requests = new Collection();

            foreach ($channels as $channel) {
                $requests->push(PublicationRequest::query()->firstOrCreate(
                    ['episode_assembly_id' => $assembly->id, 'channel_id' => $channel->id],
                    ['created_by' => $actor->id, 'production_run_id' => $productionRun?->id, 'status' => PublicationStatus::PLANNED, 'payload' => [
                        'title' => Str::limit($series->name . ' · ' . $episode->number . ' · ' . $revision->title, 97),
                        'description' => $revision->objective,
                        'made_for_kids' => ($series->specification['audience'] ?? null) === ContentAudience::CHILDREN->value,
                    ]],
                ));
            }

            return $requests;
        });
    }
}
