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
                . ' Select the outline beat assigned to this episode number. Build a clear objective, conflict, character choice and readable consequence in the supplied genre. A continuing objective is allowed; do not replay a previous conflict-and-resolution sequence with cosmetic changes.'
                . ' Use history.previous_screenplay only to identify already-used ideas, and confirmed_events as facts. An accessory or optional open_thread is not the next episode assignment.'
                . ($limit !== null && $episode->number === $limit ? ' This is the final episode: deliver the ending promised by the outline; resolve the main story unless an open ending is explicitly intended, and add no accidental new mystery.' : ' Resolve a self-contained local story. If the outline explicitly requires a cliffhanger, earn it through a concrete new development rather than stopping a repeated attempt.')
                . ' Keep the sum of all shot durations exactly equal to the requested episode duration. Use whole-second shots of 4 to 30 seconds.'
                . ' A 10-second episode should normally have one scene and one shot: setup 0-2s, one causal action 2-7s, visible payoff and reaction 7-10s. Encode the timed beats in shot.action.'
                . ' Use new_scene for a self-contained adventure; continue_scene only when this episode must complete a visible unfinished action. Do not resume an old prop interaction merely because it is the latest memory.';

            if (isset($context['cycle'])) {
                $brief = 'Write this production part of one complete video using context.cycle as the agreed plan. Entry transition_mode must match context.cycle.part.transition_mode. '
                    . 'Advance this part objective from confirmed predecessor facts. Do not add episode intros, recaps, end cards, a fresh hook or final goodbyes at technical boundaries. '
                    . 'For continuous_story preserve the shared arc; only the last part ends it. For anthology deliver the planned local story. '
                    . 'Keep the exact requested duration, whole-second shots within capabilities, and the intended final state. A part may continue the same physical scene after a local goal is resolved.';
            }

            $data = new GenerateEpisodeScriptData($brief, $episode->lock_version, (string) Str::uuid());

            if ($this->queueScript->run($episode, $data, $context, $actor->id)) {
                GenerateEpisodeScriptJob::dispatch($episode->id, $data->request_key)->afterCommit();
            }
        }

        $this->update->run($run, ProductionStatus::WAITING);
    }
}
