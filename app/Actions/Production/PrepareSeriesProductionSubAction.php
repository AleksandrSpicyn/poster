<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Data\Episodes\CreateEpisodeData;
use App\Enums\EpisodeValidity;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\RevisionStatus;
use App\Enums\SeriesOutputMode;
use App\Models\CharacterRevision;
use App\Models\ProductionRun;
use App\Models\User;
use App\Tasks\Characters\ApproveCharacterRevision;
use App\Tasks\Episodes\CreateEpisode;
use App\Tasks\Production\UpdateProductionRun;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

readonly class PrepareSeriesProductionSubAction
{
    public function __construct(
        private UpdateProductionRun $update,
        private ApproveCharacterRevision $approveCharacter,
        private CreateEpisode $createEpisode,
        #[Config('production.character_settle_seconds')] private int $settleSeconds,
    ) {}

    public function run(ProductionRun $run, User $actor): void
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
                    if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO) {
                        $this->update->run($run, ProductionStatus::WAITING, ProductionStage::FINAL_MONTAGE);
                    } else {
                        $this->update->run($run, ProductionStatus::COMPLETED, message: 'The planned series is complete.');
                    }

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
}
