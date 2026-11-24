<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Actions\Upscale\RequestVideoUpscaleAction;
use App\Data\Series\AcceptSeriesVideoData;
use App\Data\Upscale\RequestVideoUpscaleData;
use App\Enums\UpscaleExport;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Tasks\Series\AcceptSeriesAssembly;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

readonly class ReviewSeriesVideoAction
{
    public function __construct(private AcceptSeriesAssembly $accept, private RequestVideoUpscaleAction $upscale) {}

    public function run(SeriesAssembly $assembly, AcceptSeriesVideoData $data, User $actor): void
    {
        Gate::forUser($actor)->authorize('manageSeries');
        DB::transaction(function () use ($assembly, $data, $actor): void {
            $accepted = $this->accept->run($assembly, $data, $actor);
            $this->upscale->run($accepted, $actor, new RequestVideoUpscaleData(UpscaleExport::FULL_HD));
        });
    }
}
