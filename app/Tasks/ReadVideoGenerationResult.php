<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\VideoGenerator\VideoGenerator;
use App\Data\VideoResultData;
use App\Data\VideoUsageData;
use App\Enums\VideoProvider;
use App\Enums\VideoReceiptStatus;
use App\Exceptions\MalformedVideoResultException;
use App\Exceptions\VideoGeneratingException;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Repositories\GenerationProviderUsageRepository;
use Illuminate\Validation\ValidationException;

readonly class ReadVideoGenerationResult
{
    public function __construct(private GenerationProviderUsageRepository $usage) {}

    public function run(VideoGenerator $generator, VideoProvider $provider, string $externalId, Prompt|GenerationOperation $work, ?string $expectedModel = null): ?VideoResultData
    {
        try {
            $result = $generator->check($externalId);
        } catch (VideoGeneratingException|MalformedVideoResultException $exception) {
            if ($exception->usage instanceof VideoUsageData) {
                $this->assertModel($expectedModel, $exception->usage->model);
                $this->usage->record($provider, $externalId, $work, $exception->usage);
            }

            throw $exception;
        }

        if ($result instanceof VideoResultData) {
            $this->assertModel($expectedModel, $result->model);
            $receipt = $result->usage ?? new VideoUsageData(VideoReceiptStatus::SUCCEEDED, model: $result->model, duration: $result->duration);
            $this->usage->record($provider, $externalId, $work, $receipt);
        }

        return $result;
    }

    private function assertModel(?string $expected, ?string $actual): void
    {
        if ($expected !== null && $actual !== null && $expected !== $actual) {
            throw ValidationException::withMessages(['external_id' => 'The remote result belongs to a different video model.']);
        }
    }
}
