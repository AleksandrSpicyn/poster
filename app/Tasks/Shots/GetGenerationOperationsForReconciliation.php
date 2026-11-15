<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Enums\GenerationOperationStatus;
use App\Models\GenerationOperation;
use Illuminate\Database\Eloquent\Collection;

class GetGenerationOperationsForReconciliation
{
    /** @return Collection<int, GenerationOperation> */
    public function run(int $limit): Collection
    {
        return GenerationOperation::query()->with('prompt.media')
            ->where(function ($query): void {
                $query->whereIn('status', [GenerationOperationStatus::PLANNED->value, GenerationOperationStatus::SUBMITTED->value, GenerationOperationStatus::RUNNING->value])
                    ->where(function ($query): void {
                        $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now());
                    });
            })
            ->orWhere(function ($query): void {
                $query->where('status', GenerationOperationStatus::SUBMITTING)->where('updated_at', '<', now()->subMinutes(10));
            })
            ->orderBy('id')->limit(max(1, min($limit, 100)))->get();
    }
}
