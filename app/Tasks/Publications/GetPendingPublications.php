<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Enums\PublicationStatus;
use App\Models\PublicationRequest;
use Illuminate\Database\Eloquent\Collection;

class GetPendingPublications
{
    /** @return Collection<int, PublicationRequest> */
    public function run(int $limit): Collection
    {
        $limit = max(1, min(100, $limit));
        $expiredIds = PublicationRequest::query()->whereIn('status', [PublicationStatus::SUBMITTING, PublicationStatus::PUBLISHING_CONTAINER])
            ->where('submitted_at', '<', now()->subMinutes(10))
            ->orderBy('id')->limit($limit)->pluck('id');
        PublicationRequest::query()->whereKey($expiredIds)
            ->whereIn('status', [PublicationStatus::SUBMITTING, PublicationStatus::PUBLISHING_CONTAINER])
            ->where('submitted_at', '<', now()->subMinutes(10))
            ->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN, 'error_message' => 'A worker stopped before confirming the external result. Inspect its receipt before retrying.']);

        return PublicationRequest::query()->where(function ($query): void {
            $query->where('status', PublicationStatus::PLANNED)
                ->orWhere(fn($processing) => $processing->where('status', PublicationStatus::PROCESSING)
                    ->whereNotNull('container_id')->where('submitted_at', '<=', now()->subMinutes(5)));
        })->orderBy('id')->limit($limit)->get();
    }
}
