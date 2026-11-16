<?php

declare(strict_types=1);

namespace App\Actions\Shots;

use App\Jobs\SubmitShotGenerationJob;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\User;
use App\Tasks\Shots\AssertShotGenerationContext;
use App\Tasks\Shots\GetPromptShot;
use App\Tasks\Shots\RetryShotDownload;
use Illuminate\Validation\ValidationException;

readonly class RetryShotDownloadAction
{
    public function __construct(private AssertShotGenerationContext $context, private RetryShotDownload $retry, private GetPromptShot $getShot) {}

    public function run(Prompt $prompt, User $actor): GenerationOperation
    {
        $this->context->run($this->getShot->run($prompt) ?? throw ValidationException::withMessages(['shot' => 'The serial shot is missing.']));
        $operation = $this->retry->run($prompt, $actor);
        SubmitShotGenerationJob::dispatch($operation->id)->afterCommit();

        return $operation;
    }
}
