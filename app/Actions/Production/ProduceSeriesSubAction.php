<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Data\Episodes\AcceptEpisodeData;
use App\Data\Episodes\AssemblyManifestData;
use App\Data\Episodes\AssemblyShotData;
use App\Data\Episodes\CreateEpisodeData;
use App\Data\Episodes\GenerateEpisodeScriptData;
use App\Data\Episodes\PublishEpisodeData;
use App\Data\Production\EpisodeOutcomeData;
use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\PublicationStatus;
use App\Enums\RevisionStatus;
use App\Enums\ScriptGenerationStatus;
use App\Enums\TransitionMode;
use App\Jobs\BuildEpisodeAssemblyJob;
use App\Jobs\GenerateEpisodeScriptJob;
use App\Jobs\PublishEpisodeJob;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\Channel;
use App\Models\CharacterRevision;
use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\Scene;
use App\Models\User;
use App\Tasks\Characters\ApproveCharacterRevision;
use App\Tasks\Episodes\AcceptEpisode;
use App\Tasks\Episodes\ApproveEpisodeScript;
use App\Tasks\Episodes\BuildEpisodeContext;
use App\Tasks\Episodes\CreateEpisode;
use App\Tasks\Episodes\QueueEpisodeScript;
use App\Tasks\Episodes\SaveEpisodeAssembly;
use App\Tasks\Production\AssertProductionScreenplay;
use App\Tasks\Production\InspectEpisodeOutcome;
use App\Tasks\Production\UpdateProductionRun;
use App\Tasks\Publications\CreatePublicationRequest;
use App\Tasks\Shots\AssertShotGenerationContext;
use App\Tasks\Shots\CreateShotGeneration;
use App\Tasks\Shots\PrepareShotGenerationInput;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

readonly class ProduceSeriesSubAction
{
    public function __construct(
        private UpdateProductionRun $update,
        private ApproveCharacterRevision $approveCharacter,
        private CreateEpisode $createEpisode,
        private BuildEpisodeContext $context,
        private QueueEpisodeScript $queueScript,
        private ApproveEpisodeScript $approveScript,
        private AssertProductionScreenplay $assertScreenplay,
        private AssertShotGenerationContext $shotContext,
        private PrepareShotGenerationInput $shotInput,
        private CreateShotGeneration $createTake,
        private SaveEpisodeAssembly $saveAssembly,
        private InspectEpisodeOutcome $inspect,
        private AcceptEpisode $accept,
        private CreatePublicationRequest $publish,
        #[Config('production.character_settle_seconds')] private int $settleSeconds,
    ) {}

    public function run(ProductionRun $run, User $actor): void
    {
        match ($run->stage) {
            ProductionStage::PREPARING => $this->prepare($run, $actor),
            ProductionStage::SCREENPLAY => $this->screenplay($run, $actor),
            ProductionStage::MEDIA => $this->media($run, $actor),
            ProductionStage::MONTAGE => $this->montage($run),
            ProductionStage::STORY => $this->story($run, $actor),
            ProductionStage::PUBLICATION => $this->publication($run, $actor),
        };
    }

    private function prepare(ProductionRun $run, User $actor): void
    {
        $series = $run->series ?? throw new RuntimeException('The production series is unavailable.');
        $characters = $series->characters->whereNull('archived_at');

        if ($characters->isEmpty()) {
            $this->update->run($run, ProductionStatus::WAITING, message: 'Add the series characters and upload their reference images.');

            return;
        }

        $drafts = [];

        foreach ($characters->sortBy('id') as $character) {
            $draft = $character->revisions->first(fn(CharacterRevision $revision): bool => $revision->status === RevisionStatus::DRAFT);

            if ($draft !== null) {
                if ($draft->getMedia(CharacterRevision::REFERENCES)->isEmpty()) {
                    $this->update->run($run, ProductionStatus::WAITING, message: 'Upload reference images for ' . $character->name . '.');

                    return;
                }

                if ($draft->updated_at?->greaterThan(now()->subSeconds($this->settleSeconds))) {
                    $this->update->run($run, ProductionStatus::WAITING, message: 'Waiting for the character uploads to finish.');

                    return;
                }

                $drafts[] = $draft;
            } elseif ($character->currentRevision === null) {
                $this->update->run($run, ProductionStatus::WAITING, message: 'Create an appearance and upload references for ' . $character->name . '.');

                return;
            }
        }

        DB::transaction(function () use ($drafts, $actor): void {
            foreach ($drafts as $draft) {
                $this->approveCharacter->run($draft, $actor, $draft->lock_version);
            }
        });

        $episode = $run->episode;

        if ($episode === null) {
            $latest = $series->episodes->first();

            if ($latest !== null && $latest->accepted_assembly_id === null) {
                $episode = $latest;
            } else {
                $number = ($latest->number ?? 0) + 1;

                if ($series->episode_limit !== null && $number > $series->episode_limit) {
                    $this->update->run($run, ProductionStatus::COMPLETED, message: 'The planned series is complete.');

                    return;
                }

                $episode = $this->createEpisode->run($series, new CreateEpisodeData('Episode ' . $number));
            }
        }

        if ($episode->validity !== EpisodeValidity::CURRENT) {
            $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: 'An earlier episode changed. Review the continuation before resuming.');

            return;
        }

        $this->update->run($run, ProductionStatus::WAITING, ProductionStage::SCREENPLAY, attributes: ['episode_id' => $episode->id]);
    }

    private function screenplay(ProductionRun $run, User $actor): void
    {
        $episode = $run->episode ?? throw new RuntimeException('The production episode is unavailable.');
        $revision = $episode->currentRevision;

        if ($revision !== null) {
            $this->assertScreenplay->run($revision);
            $this->approveScript->run($revision, $actor, useGeneratedTitle: true);
            $this->update->run($run, ProductionStatus::WAITING, ProductionStage::MEDIA);

            return;
        }

        if (in_array($episode->script_generation_status, [ScriptGenerationStatus::FAILED, ScriptGenerationStatus::OUTCOME_UNKNOWN], true)) {
            $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: 'The screenplay could not be generated. Review the episode error before retrying.');

            return;
        }

        if (! in_array($episode->script_generation_status, [ScriptGenerationStatus::QUEUED, ScriptGenerationStatus::RUNNING], true)) {
            $context = $this->context->run($episode);
            $limit = $run->series?->episode_limit;
            $brief = 'Create episode ' . $episode->number . ($limit === null ? ' of an ongoing series.' : ' of ' . $limit . '.')
                . ' Follow specification.story_outline as the overall story, narrative_rules as constraints, and the confirmed history as facts.'
                . ' Advance the story instead of repeating earlier events. Give this episode its own clear objective and payoff.'
                . ($limit !== null && $episode->number === $limit ? ' This is the final episode: resolve the main story.' : '')
                . ' Keep the sum of all shot durations exactly equal to the requested episode duration. Use whole-second shots of 4 to 30 seconds.'
                . ' A 10-second episode should normally have one scene and one shot. Use new_scene for a self-contained adventure; continue_scene only when continuing a visible unfinished action.';
            $data = new GenerateEpisodeScriptData($brief, $episode->lock_version, (string) Str::uuid());

            if ($this->queueScript->run($episode, $data, $context, $actor->id)) {
                GenerateEpisodeScriptJob::dispatch($episode->id, $data->request_key)->afterCommit();
            }
        }

        $this->update->run($run, ProductionStatus::WAITING);
    }

    private function media(ProductionRun $run, User $actor): void
    {
        $episode = $run->episode ?? throw new RuntimeException('The production episode is unavailable.');
        $revision = $episode->currentRevision ?? throw new RuntimeException('The production screenplay is unavailable.');
        $shots = $revision->scenes->flatMap(fn(Scene $scene) => $scene->shots)->values();
        $selections = [];
        $previousPromptId = null;

        foreach ($shots as $shot) {
            $latest = $shot->prompts->sortByDesc('take_number')->first();

            if ($latest !== null && $latest->operations->contains(fn(GenerationOperation $operation): bool => $operation->status === GenerationOperationStatus::SUBMISSION_UNKNOWN)) {
                $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: 'A generation result is unknown. Resolve the existing provider request before another paid submission.');

                return;
            }

            $ready = $shot->prompts->where('generated', true)->sortByDesc('take_number')->first();

            if ($ready !== null) {
                $selections[] = new AssemblyShotData($ready->id, 0, $shot->duration_ms);
                $previousPromptId = $ready->id;

                continue;
            }

            if ($latest !== null) {
                $blocked = $latest->operations->contains(fn(GenerationOperation $operation): bool => in_array($operation->status, [GenerationOperationStatus::FAILED, GenerationOperationStatus::CANCELLED, GenerationOperationStatus::ABANDONED], true));
                $this->update->run(
                    $run,
                    $blocked ? ProductionStatus::NEEDS_ATTENTION : ProductionStatus::WAITING,
                    message: $blocked ? 'A shot stopped or failed. Review the existing take before requesting another generation.' : null,
                );

                return;
            }

            $shot = $this->shotContext->run($shot);
            $source = $shot->transition_mode === TransitionMode::NEW_SCENE ? null : $previousPromptId;
            $snapshot = $this->shotInput->run($shot, $actor, $source);
            $take = $this->createTake->run($shot, $snapshot, 'production-' . $run->id . '-shot-' . $shot->id, false, $run);
            $operation = $take->generationOperations->first(fn(GenerationOperation $operation): bool => $operation->stage === GenerationOperationStage::IMAGE && $operation->status === GenerationOperationStatus::PLANNED);

            if ($operation !== null) {
                SubmitShotGenerationJob::dispatch($operation->id)->afterCommit();
            }

            $this->update->run($run, ProductionStatus::WAITING);

            return;
        }

        DB::transaction(function () use ($revision, $selections, $run): void {
            $assembly = $this->saveAssembly->run($revision, new AssemblyManifestData($selections));
            $this->update->run($run, ProductionStatus::WAITING, ProductionStage::MONTAGE, attributes: ['episode_assembly_id' => $assembly->id]);

            if ($assembly->status === AssemblyStatus::QUEUED) {
                BuildEpisodeAssemblyJob::dispatch($assembly->id)->afterCommit();
            }
        });
    }

    private function montage(ProductionRun $run): void
    {
        $assembly = $run->assembly ?? throw new RuntimeException('The production montage is unavailable.');

        if (in_array($assembly->status, [AssemblyStatus::READY, AssemblyStatus::ACCEPTED], true)) {
            $this->update->run($run, ProductionStatus::WAITING, ProductionStage::STORY);

            return;
        }

        $failed = $assembly->status === AssemblyStatus::FAILED;
        $this->update->run(
            $run,
            $failed ? ProductionStatus::NEEDS_ATTENTION : ProductionStatus::WAITING,
            message: $failed ? 'The montage could not be built. Review its error before resuming.' : null,
        );
    }

    private function story(ProductionRun $run, User $actor): void
    {
        $assembly = $run->assembly ?? throw new RuntimeException('The production montage is unavailable.');

        if ($assembly->status !== AssemblyStatus::ACCEPTED) {
            if ($run->outcome_snapshot === null) {
                $this->update->run($run, ProductionStatus::RUNNING);
                $outcome = $this->inspect->run($assembly);
                /** @var array<string, mixed> $snapshot */
                $snapshot = $outcome->toArray();
                $this->update->run($run, ProductionStatus::RUNNING, attributes: ['outcome_snapshot' => $snapshot]);
            } else {
                $outcome = EpisodeOutcomeData::validateAndCreate($run->outcome_snapshot);
            }

            if (! $outcome->usable) {
                $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: 'The montage needs review: ' . $outcome->review_notes);

                return;
            }

            $this->accept->run($assembly, new AcceptEpisodeData(
                $assembly->lock_version,
                $outcome->confirmed_events,
                $outcome->character_state,
                $outcome->open_threads ?? '',
                $outcome->scene_closed,
            ), $actor);
        }

        $this->update->run($run, ProductionStatus::WAITING, ProductionStage::PUBLICATION);
    }

    private function publication(ProductionRun $run, User $actor): void
    {
        $assembly = $run->assembly ?? throw new RuntimeException('The accepted montage is unavailable.');
        $channels = $run->project->channels->filter(fn(Channel $channel): bool => in_array($channel->type->value, ['youtube', 'instagram'], true));
        $ids = array_values($channels->map(fn(Channel $channel): int => $channel->id)->all());
        $requests = $this->publish->run($assembly, new PublishEpisodeData($ids), $actor, $run);

        foreach ($requests as $request) {
            if ($request->status === PublicationStatus::PLANNED && $request->wasRecentlyCreated) {
                PublishEpisodeJob::dispatch($request->id)->afterCommit();
            }
        }

        if ($requests->isNotEmpty() && $requests->every(fn($request): bool => $request->status === PublicationStatus::PUBLISHED)) {
            $this->update->run($run, ProductionStatus::COMPLETED);

            return;
        }

        $blocked = $requests->contains(fn($request): bool => in_array($request->status, [PublicationStatus::FAILED, PublicationStatus::SUBMISSION_UNKNOWN], true));
        $this->update->run(
            $run,
            $blocked ? ProductionStatus::NEEDS_ATTENTION : ProductionStatus::WAITING,
            message: $blocked ? 'A publication needs attention. Keep its existing receipt; do not send a duplicate.' : null,
        );
    }
}
