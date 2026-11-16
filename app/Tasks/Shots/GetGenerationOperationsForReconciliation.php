<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Models\GenerationOperation;
use App\Repositories\GenerationOperationRepository;
use Illuminate\Database\Eloquent\Collection;

class GetGenerationOperationsForReconciliation
{
    public function __construct(private readonly GenerationOperationRepository $generationOperations) {}

    /** @return Collection<int, GenerationOperation> */
    public function run(int $limit): Collection
    {
        return $this->generationOperations->forReconciliation($limit);
    }
}
