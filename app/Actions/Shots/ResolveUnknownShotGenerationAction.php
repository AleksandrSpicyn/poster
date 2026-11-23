<?php

declare(strict_types=1);

namespace App\Actions\Shots;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Data\Shots\ResolveShotGenerationData;
use App\Data\VideoResultData;
use App\Enums\GenerationOperationStatus;
use App\Exceptions\VideoGeneratingException;
use App\Jobs\CheckShotGenerationJob;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\GenerationOperation;
use App\Models\User;
use App\Tasks\ReadVideoGenerationResult;
use App\Tasks\Shots\AssertShotGenerationContext;
use App\Tasks\Shots\GetShotResolutionOperation;
use App\Tasks\Shots\UpdateGenerationOperation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

readonly class ResolveUnknownShotGenerationAction
{
    public function __construct(
        private GetShotResolutionOperation $get,
        private AssertShotGenerationContext $context,
        private VideoGeneratorFactory $generators,
        private ReadVideoGenerationResult $read,
        private UpdateGenerationOperation $update,
    ) {}

    public function run(GenerationOperation $operation, ResolveShotGenerationData $data, User $actor): void
    {
        $acquired = Cache::lock('shot-operation:' . $operation->id, 150)->get(function () use ($operation, $data, $actor): bool {
            $operation = $this->get->run($operation, $data->external_id, $actor);

            if ($operation->status !== GenerationOperationStatus::SUBMISSION_UNKNOWN) {
                return true;
            }

            $this->context->run($operation->prompt->shot ?? throw new RuntimeException('Shot missing.'));
            $provider = $operation->provider ?? throw new RuntimeException('Provider missing.');
            $generator = $this->generators->make($provider);
            $generator->capabilities($operation->model);

            $metadata = [
                ...($operation->result_snapshot ?? []), 'resolved_by' => $actor->id,
                'resolution_note' => $data->note, 'resolved_at' => now()->toISOString(),
            ];

            try {
                $result = $this->read->run($generator, $provider, $data->external_id, $operation, $operation->model);
            } catch (VideoGeneratingException $exception) {
                $this->update->run($operation, GenerationOperationStatus::FAILED, [
                    'external_id' => $data->external_id, 'provider' => $operation->provider,
                    'model' => $operation->model, 'result_snapshot' => $metadata, 'error_code' => $exception::class,
                ], GenerationOperationStatus::SUBMISSION_UNKNOWN);

                return true;
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (Throwable) {
                throw ValidationException::withMessages(['external_id' => 'The remote receipt could not be verified. No new generation was sent.']);
            }

            $attributes = ['external_id' => $data->external_id, 'provider' => $operation->provider, 'model' => $operation->model, 'error_code' => null];

            if (! $result instanceof VideoResultData) {
                $this->update->run(
                    $operation,
                    GenerationOperationStatus::SUBMITTED,
                    [...$attributes,
                        'result_snapshot' => [...$metadata, 'polling_limit' => $operation->check_count + $generator->pollingAttempts()]],
                    GenerationOperationStatus::SUBMISSION_UNKNOWN,
                );
                CheckShotGenerationJob::dispatch($operation->id)->delay(now()->addMinute())->afterCommit();

                return true;
            }

            $metadata = [...$metadata, 'video_url' => $result->videoUrl, 'audio_url' => $result->audioUrl,
                'last_frame_url' => $result->lastFrameUrl, 'duration' => $result->duration, 'model' => $result->model];
            $missingAudio = data_get($operation->request_snapshot, 'video.generate_audio') === true && $result->audioUrl === null;
            $next = $this->update->run(
                $operation,
                $missingAudio ? GenerationOperationStatus::FAILED : GenerationOperationStatus::SUCCEEDED,
                [...$attributes, 'result_snapshot' => $metadata, 'error_code' => $missingAudio ? 'missing_requested_audio' : null],
                GenerationOperationStatus::SUBMISSION_UNKNOWN,
            );

            if ($next instanceof GenerationOperation) {
                SubmitShotGenerationJob::dispatch($next->id)->afterCommit();
            }

            return true;
        });

        if ($acquired === false) {
            throw ValidationException::withMessages(['operation' => 'This operation is already being processed. Retry after its current check completes.']);
        }
    }
}
