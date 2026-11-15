<?php

declare(strict_types=1);

namespace App\Actions\Shots;

use App\Data\Shots\AbandonShotGenerationData;
use App\Models\GenerationOperation;
use App\Models\User;
use App\Tasks\Shots\AbandonShotGeneration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

readonly class AbandonShotGenerationAction
{
    public function __construct(private AbandonShotGeneration $abandon) {}

    public function run(GenerationOperation $operation, AbandonShotGenerationData $data, User $actor): void
    {
        $acquired = Cache::lock('shot-operation:' . $operation->id, 150)->get(function () use ($operation, $data, $actor): bool {
            $this->abandon->run($operation, $data, $actor);

            return true;
        });

        if ($acquired === false) {
            throw ValidationException::withMessages(['operation' => 'This operation is already being processed. Retry after its current check completes.']);
        }
    }
}
