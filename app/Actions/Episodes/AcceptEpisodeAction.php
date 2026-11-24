<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Actions\Upscale\RequestVideoUpscaleAction;
use App\Data\Episodes\AcceptEpisodeData;
use App\Data\Upscale\RequestVideoUpscaleData;
use App\Enums\SeriesOutputMode;
use App\Enums\UpscaleExport;
use App\Models\EpisodeAssembly;
use App\Models\ProductionRun;
use App\Models\User;
use App\Repositories\SeriesRepository;
use App\Tasks\Episodes\AcceptEpisode;
use Illuminate\Support\Facades\DB;

readonly class AcceptEpisodeAction
{
    public function __construct(private AcceptEpisode $accept, private SeriesRepository $series, private RequestVideoUpscaleAction $upscale) {}

    public function run(EpisodeAssembly $assembly, AcceptEpisodeData $data, User $actor, ?ProductionRun $run = null): EpisodeAssembly
    {
        return DB::transaction(function () use ($assembly, $data, $actor, $run): EpisodeAssembly {
            $accepted = $this->accept->run($assembly, $data, $actor, $run);
            $series = $this->series->forAssembly($accepted);

            if ($series->id === config('services.upscale.series_id') && $series->output_mode === SeriesOutputMode::EPISODES) {
                $this->upscale->run($accepted, $actor, new RequestVideoUpscaleData(UpscaleExport::FULL_HD));
            }

            return $accepted;
        });
    }
}
