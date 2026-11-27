<?php

declare(strict_types=1);

namespace App\Actions\Shots;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\VideoProvider;
use App\Exceptions\CodexBusyException;
use App\Exceptions\VideoGenerationUncertainException;
use App\Jobs\CheckShotGenerationJob;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\GenerationOperation;
use App\Support\Integrations\Payload;
use App\Tasks\Shots\AssertShotGenerationContext;
use App\Tasks\Shots\BuildShotVideoRequest;
use App\Tasks\Shots\ClaimGenerationOperation;
use App\Tasks\Shots\DownloadShotVideo;
use App\Tasks\Shots\RenderShotImage;
use App\Tasks\Shots\UpdateGenerationOperation;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

readonly class SubmitShotGenerationAction
{
    public function __construct(
        private ClaimGenerationOperation $claim,
        private AssertShotGenerationContext $context,
        private RenderShotImage $image,
        private BuildShotVideoRequest $request,
        private VideoGeneratorFactory $generators,
        private DownloadShotVideo $download,
        private UpdateGenerationOperation $update,
    ) {}

    public function run(int $operationId): void
    {
        Cache::lock('shot-operation:' . $operationId, 510)->get(function () use ($operationId): void {
            $operation = $this->claim->run($operationId);

            if (! $operation instanceof GenerationOperation) {
                return;
            }

            $submittedId = null;
            $submittedProvider = null;
            $submittedModel = null;

            try {
                $prompt = $operation->prompt ?? throw new RuntimeException('The generation attempt no longer exists.');

                if ($operation->stage !== GenerationOperationStage::DOWNLOAD) {
                    $this->context->run($prompt->shot ?? throw new RuntimeException('The shot no longer exists.'));
                }

                if ($operation->stage === GenerationOperationStage::IMAGE) {
                    $media = $this->image->run($operation);
                    $next = $this->update->run($operation, GenerationOperationStatus::SUCCEEDED, ['result_snapshot' => ['image_media_id' => $media->id]]);
                } elseif ($operation->stage === GenerationOperationStage::VIDEO) {
                    $configuration = $operation->request_snapshot['video'] ?? null;
                    $provider = VideoProvider::from(Payload::string(is_array($configuration) ? ($configuration['provider'] ?? null) : null, 'provider'));
                    $request = $this->request->run($operation);
                    $generator = $this->generators->make($provider);
                    $generator->capabilities($request->model)->assertSupports($request);
                    $submittedId = $generator->generate($request);
                    $submittedProvider = $provider;
                    $submittedModel = $request->model;
                    $this->update->run($operation, GenerationOperationStatus::SUBMITTED, [
                        'external_id' => $submittedId,
                        'provider' => $provider,
                        'model' => $request->model,
                    ]);
                    CheckShotGenerationJob::dispatch($operation->id)->delay(now()->addMinute())->afterCommit();

                    return;
                } else {
                    $media = $this->download->run($operation);
                    $next = $this->update->run($operation, GenerationOperationStatus::SUCCEEDED, ['result_snapshot' => [...($operation->result_snapshot ?? []), 'video_media_id' => $media->id]]);
                }

                if ($next instanceof GenerationOperation && $next->status === GenerationOperationStatus::PLANNED) {
                    SubmitShotGenerationJob::dispatch($next->id)->afterCommit();
                }
            } catch (CodexBusyException) {
                $this->update->run($operation, GenerationOperationStatus::PLANNED, ['started_at' => null, 'error_code' => null]);
                SubmitShotGenerationJob::dispatch($operation->id)->delay(now()->addMinute())->afterCommit();
            } catch (VideoGenerationUncertainException $exception) {
                $this->update->run($operation, GenerationOperationStatus::SUBMISSION_UNKNOWN, ['error_code' => $exception::class]);
            } catch (Throwable $exception) {
                if ($submittedId !== null) {
                    $this->update->run($operation, GenerationOperationStatus::SUBMITTED, [
                        'external_id' => $submittedId,
                        'provider' => $submittedProvider,
                        'model' => $submittedModel,
                        'error_code' => 'post_submission_delivery_interrupted',
                    ]);

                    return;
                }

                $extendedLimit = $operation->result_snapshot['retry_limit'] ?? null;
                $limit = is_int($extendedLimit) ? max(3, $extendedLimit) : 3;
                $retryDownload = $operation->stage === GenerationOperationStage::DOWNLOAD && $operation->check_count < $limit;
                $this->update->run($operation, $retryDownload ? GenerationOperationStatus::PLANNED : GenerationOperationStatus::FAILED, ['error_code' => $exception::class]);

                if ($retryDownload) {
                    SubmitShotGenerationJob::dispatch($operation->id)->delay(now()->addMinute())->afterCommit();
                }
            }
        });
    }
}
