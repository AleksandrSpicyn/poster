<?php

declare(strict_types=1);

namespace App\Actions\Shots;

use App\Jobs\SubmitShotGenerationJob;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\Series;
use App\Tasks\Shots\ApproveOpeningFrame;
use Illuminate\Support\Facades\DB;

readonly class ApproveOpeningFrameAction
{
    public function __construct(private ApproveOpeningFrame $approve) {}

    public function run(Series $series, Prompt $take, int $operation, ?int $assetId): void
    {
        DB::transaction(function () use ($series, $take, $operation, $assetId): void {
            $next = $this->approve->run($series, $take, $operation, $assetId);

            if ($next instanceof GenerationOperation) {
                SubmitShotGenerationJob::dispatch($next->id)->afterCommit();
            }
        });
    }
}
