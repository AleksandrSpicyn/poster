<?php

declare(strict_types=1);

namespace App\Actions\Shots;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Data\VideoResultData;
use App\Enums\GenerationOperationStatus;
use App\Exceptions\VideoGeneratingException;
use App\Exceptions\VideoGenerationPendingException;
use App\Jobs\CheckShotGenerationJob;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\GenerationOperation;
use App\Tasks\ReadVideoGenerationResult;
use App\Tasks\Shots\ClaimGenerationOperation;
use App\Tasks\Shots\UpdateGenerationOperation;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

readonly class CheckShotGenerationAction
{
    public function __construct(private ClaimGenerationOperation $claim, private VideoGeneratorFactory $generators, private ReadVideoGenerationResult $read, private UpdateGenerationOperation $update) {}

    public function run(int $operationId): void
    {
        Cache::lock('shot-operation:' . $operationId, 150)->get(function () use ($operationId): void {
            $operation = $this->claim->run($operationId, polling: true);

            if (! $operation instanceof GenerationOperation) {
                return;
            }

            try {
                $provider = $operation->provider ?? throw new RuntimeException('The submitted provider is missing.');
                $generator = $this->generators->make($provider);

                $extendedLimit = $operation->result_snapshot['polling_limit'] ?? null;
                $limit = is_int($extendedLimit) ? max($extendedLimit, $generator->pollingAttempts()) : $generator->pollingAttempts();

                if ($operation->check_count > $limit) {
                    $this->update->run($operation, GenerationOperationStatus::SUBMISSION_UNKNOWN, ['error_code' => 'polling_window_exhausted']);

                    return;
                }

                $result = $this->read->run($generator, $provider, $operation->external_id ?? throw new RuntimeException('The remote task ID is missing.'), $operation);

                if (! $result instanceof VideoResultData) {
                    CheckShotGenerationJob::dispatch($operation->id)->delay(now()->addMinute())->afterCommit();

                    return;
                }

                $receipt = [
                    ...($operation->result_snapshot ?? []),
                    'video_url' => $result->videoUrl,
                    'audio_url' => $result->audioUrl,
                    'last_frame_url' => $result->lastFrameUrl,
                    'duration' => $result->duration,
                    'model' => $result->model,
                ];
                $missingAudio = data_get($operation->request_snapshot, 'video.generate_audio') === true && $result->audioUrl === null;
                $wrongModel = $result->model !== null && $result->model !== $operation->model;

                if ($missingAudio || $wrongModel) {
                    $this->update->run($operation, GenerationOperationStatus::FAILED, ['result_snapshot' => $receipt,
                        'error_code' => $wrongModel ? 'unexpected_result_model' : 'missing_requested_audio']);

                    return;
                }

                $next = $this->update->run($operation, GenerationOperationStatus::SUCCEEDED, ['result_snapshot' => $receipt]);

                if ($next instanceof GenerationOperation) {
                    SubmitShotGenerationJob::dispatch($next->id)->afterCommit();
                }
            } catch (VideoGenerationPendingException) {
                CheckShotGenerationJob::dispatch($operation->id)->delay(now()->addMinute())->afterCommit();
            } catch (VideoGeneratingException $exception) {
                $this->update->run($operation, GenerationOperationStatus::FAILED, ['error_code' => $exception::class]);
            } catch (Throwable $exception) {
                $this->update->run($operation, GenerationOperationStatus::SUBMISSION_UNKNOWN, ['error_code' => $exception::class]);
            }
        });
    }
}
