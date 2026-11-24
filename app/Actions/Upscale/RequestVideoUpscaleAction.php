<?php

declare(strict_types=1);

namespace App\Actions\Upscale;

use App\Classes\Media\Upscale\VideoUpscalerFactory;
use App\Data\Episodes\EpisodeAssemblyPlanData;
use App\Data\Upscale\RequestVideoUpscaleData;
use App\Enums\UpscaleOperationStatus;
use App\Jobs\AdvanceVideoUpscaleJob;
use App\Models\EpisodeAssembly;
use App\Models\User;
use App\Models\VideoUpscaleOperation;
use App\Repositories\VideoUpscaleOperationRepository;

final readonly class RequestVideoUpscaleAction
{
    public function __construct(private VideoUpscalerFactory $drivers, private VideoUpscaleOperationRepository $operations) {}

    public function run(EpisodeAssembly $assembly, User $actor, RequestVideoUpscaleData $data): VideoUpscaleOperation
    {
        $provider = $this->drivers->primary();
        $capabilities = $this->drivers->make($provider)->capabilities();
        $duration = EpisodeAssemblyPlanData::fromManifest($assembly->manifest)->durationMs;
        $operation = $this->operations->plan($assembly, $actor, $provider, $capabilities, $data->export, $data->export->estimate($duration), $data->retry_operation_id);

        if ($operation->status === UpscaleOperationStatus::PENDING) {
            AdvanceVideoUpscaleJob::dispatch($operation->id)->afterCommit();
        }

        return $operation;
    }
}
