<?php

declare(strict_types=1);

namespace App\Actions\Upscale;

use App\Classes\Media\Upscale\VideoUpscalerFactory;
use App\Data\Episodes\EpisodeAssemblyPlanData;
use App\Data\Upscale\RequestVideoUpscaleData;
use App\Enums\UpscaleOperationStatus;
use App\Jobs\AdvanceVideoUpscaleJob;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Models\VideoUpscaleOperation;
use App\Repositories\VideoUpscaleOperationRepository;
use App\Tasks\Upscale\UpscaleMedia;
use Illuminate\Support\Facades\DB;

final readonly class RequestVideoUpscaleAction
{
    public function __construct(private VideoUpscalerFactory $drivers, private VideoUpscaleOperationRepository $operations, private UpscaleMedia $media) {}

    public function run(EpisodeAssembly|SeriesAssembly|Prompt $assembly, User $actor, RequestVideoUpscaleData $data): VideoUpscaleOperation
    {
        $provider = $this->drivers->primary();
        $capabilities = $this->drivers->make($provider)->capabilities();
        $snapshot = $assembly instanceof EpisodeAssembly && ! empty($assembly->manifest['shots'])
            ? $assembly->manifest
            : $this->media->sourceSnapshot($assembly);
        $duration = is_int($snapshot['duration_ms'] ?? null) ? $snapshot['duration_ms'] : EpisodeAssemblyPlanData::fromManifest($snapshot)->durationMs;

        if ($duration > $capabilities->maxDurationMs) {
            $snapshot = $this->media->sourceSnapshot($assembly);
            $duration = $snapshot['duration_ms'];
            $snapshot['part_count'] = (int) ceil($duration / $capabilities->maxDurationMs);

            return DB::transaction(function () use ($assembly, $actor, $provider, $capabilities, $data, $snapshot, $duration): VideoUpscaleOperation {
                $parent = $this->operations->plan($assembly, $actor, $provider, $capabilities, $data->export, 0, $snapshot, $data->retry_operation_id);

                if (! $parent->wasRecentlyCreated) {
                    return $parent;
                }

                for ($start = 0; $start < $duration; $start += $capabilities->maxDurationMs) {
                    $length = min($duration - $start, $capabilities->maxDurationMs);
                    $part = ['media_id' => $snapshot['media_id'], 'checksum' => $snapshot['checksum'], 'start_ms' => $start, 'duration_ms' => $length];
                    $child = $this->operations->plan($assembly, $actor, $provider, $capabilities, $data->export, $data->export->estimate($length), $part, parentId: $parent->id);
                    AdvanceVideoUpscaleJob::dispatch($child->id)->afterCommit();
                }

                AdvanceVideoUpscaleJob::dispatch($parent->id)->afterCommit();

                return $parent;
            });
        }

        $operation = $this->operations->plan($assembly, $actor, $provider, $capabilities, $data->export, $data->export->estimate($duration), $snapshot, $data->retry_operation_id);

        if ($operation->status === UpscaleOperationStatus::PENDING) {
            AdvanceVideoUpscaleJob::dispatch($operation->id)->afterCommit();
        }

        return $operation;
    }
}
