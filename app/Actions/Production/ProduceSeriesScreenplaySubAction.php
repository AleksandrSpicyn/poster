<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Data\Episodes\GenerateEpisodeScriptData;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\ScriptGenerationStatus;
use App\Jobs\GenerateEpisodeScriptJob;
use App\Models\ProductionRun;
use App\Models\User;
use App\Tasks\Episodes\ApproveEpisodeScript;
use App\Tasks\Episodes\BuildEpisodeContext;
use App\Tasks\Episodes\QueueEpisodeScript;
use App\Tasks\Production\AssertProductionScreenplay;
use App\Tasks\Production\UpdateProductionRun;
use Illuminate\Support\Str;
use RuntimeException;

readonly class ProduceSeriesScreenplaySubAction
{
    public function __construct(
        private UpdateProductionRun $update,
        private BuildEpisodeContext $context,
        private QueueEpisodeScript $queueScript,
        private ApproveEpisodeScript $approveScript,
        private AssertProductionScreenplay $assertScreenplay,
    ) {}

    public function run(ProductionRun $run, User $actor): void
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
                . ' Select the outline beat assigned to this episode number. Build one fresh objective, obstacle, character choice and visible payoff; do not replay a previous objective or gag with cosmetic changes.'
                . ' Use history.previous_screenplay only to identify already-used ideas, and confirmed_events as facts. An accessory or optional open_thread is not the next episode assignment.'
                . ($limit !== null && $episode->number === $limit ? ' This is the final episode: visibly resolve the main story and the local goal; leave no cliffhanger or new mystery.' : ' Resolve the local goal within this episode even when the larger story continues.')
                . ' Keep the sum of all shot durations exactly equal to the requested episode duration. Use whole-second shots of 4 to 30 seconds.'
                . ' A 10-second episode should normally have one scene and one shot: setup 0-2s, one causal action 2-7s, visible payoff and reaction 7-10s. Encode the timed beats in shot.action.'
                . ' Use new_scene for a self-contained adventure; continue_scene only when this episode must complete a visible unfinished action. Do not resume an old prop interaction merely because it is the latest memory.';
            $data = new GenerateEpisodeScriptData($brief, $episode->lock_version, (string) Str::uuid());

            if ($this->queueScript->run($episode, $data, $context, $actor->id)) {
                GenerateEpisodeScriptJob::dispatch($episode->id, $data->request_key)->afterCommit();
            }
        }

        $this->update->run($run, ProductionStatus::WAITING);
    }
}
