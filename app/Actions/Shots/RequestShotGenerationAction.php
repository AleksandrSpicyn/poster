<?php

declare(strict_types=1);

namespace App\Actions\Shots;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\Prompt;
use App\Models\Shot;
use App\Models\User;
use App\Tasks\Shots\AssertShotGenerationContext;
use App\Tasks\Shots\CreateShotGeneration;
use App\Tasks\Users\RefreshUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

readonly class RequestShotGenerationAction
{
    public function __construct(private AssertShotGenerationContext $context, private PrepareShotGenerationInputSubAction $prepare, private CreateShotGeneration $create, private RefreshUser $refreshUser) {}

    public function run(Shot $shot, User $actor, ?int $sourcePromptId = null, ?string $requestKey = null, bool $newTake = false): Prompt
    {
        Gate::forUser($this->refreshUser->run($actor))->authorize('manageSeries', $shot);

        if ($requestKey !== null && (strlen($requestKey) > 100 || trim($requestKey) === '')) {
            throw ValidationException::withMessages(['generation' => 'A valid request key is required.']);
        }

        $shot = $this->context->run($shot);
        $snapshot = $this->prepare->run($shot, $actor, $sourcePromptId);
        $prompt = $this->create->run($shot, $snapshot, $requestKey, $newTake);
        $operation = $prompt->generationOperations->first(fn($operation): bool => $operation->stage === GenerationOperationStage::IMAGE && $operation->status === GenerationOperationStatus::PLANNED);

        if ($operation !== null) {
            SubmitShotGenerationJob::dispatch($operation->id)->afterCommit();
        }

        return $prompt;
    }
}
