<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Production\AdvanceProductionAction;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Exceptions\CodexBusyException;
use App\Tasks\Production\GetProductionRun;
use App\Tasks\Production\UpdateProductionRun;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdvanceProductionJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 480;

    public int $uniqueFor = 60;

    public function __construct(public readonly int $runId) {}

    public function uniqueId(): string
    {
        return 'production:' . $this->runId;
    }

    public function handle(AdvanceProductionAction $action, GetProductionRun $getRun, UpdateProductionRun $update): void
    {
        $lock = Cache::lock($this->uniqueId() . ':processing', 510);

        if (! $lock->get()) {
            return;
        }

        try {
            $action->run($this->runId);
        } catch (CodexBusyException) {
            $run = $getRun->run($this->runId);
            $update->run(
                $run,
                ProductionStatus::WAITING,
                stage: $run->series_id === null && $run->prompt_id === null ? ProductionStage::PREPARING : null,
                message: 'Waiting for the shared AI session.',
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $update->run(
                $getRun->run($this->runId),
                ProductionStatus::NEEDS_ATTENTION,
                message: is_string($message) ? $message : 'Production input changed. Review it before resuming.',
            );
        } catch (Throwable $exception) {
            Log::error('Automatic production stopped.', ['run_id' => $this->runId, 'exception' => $exception::class]);
            $update->run(
                $getRun->run($this->runId),
                ProductionStatus::NEEDS_ATTENTION,
                message: 'Production stopped after an error. Inspect the saved operations before retrying.',
            );
        } finally {
            $lock->release();
        }

        $run = $getRun->run($this->runId);

        if ($run->status->isActive()) {
            self::dispatch($run->id)->delay(now()->addSeconds(15));
        }
    }
}
