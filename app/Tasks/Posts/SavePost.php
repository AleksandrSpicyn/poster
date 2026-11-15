<?php

declare(strict_types=1);

namespace App\Tasks\Posts;

use App\Data\PostData;
use App\Enums\PublicationStatus;
use App\Events\PostCreated;
use App\Models\PublicationRequest;
use Illuminate\Support\Facades\DB;

/**
 * Записывает результат публикации через связь канала и отправляет PostCreated
 * для запуска уведомлений; внешний upload выполняет Publisher.
 */
class SavePost
{
    public function run(PostData $dto): void
    {
        DB::transaction(function () use ($dto): void {
            $request = $dto->publicationRequestId === null ? null : PublicationRequest::query()->lockForUpdate()->findOrFail($dto->publicationRequestId);

            if ($request?->status === PublicationStatus::PUBLISHED) {
                return;
            }

            $post = $dto->channel->posts()->create([
                'title' => $dto->title,
                'description' => $dto->description,
                'url' => $dto->url,
                'external_id' => $dto->externalId,
                'prompt_id' => $dto->prompt?->id,
                'episode_assembly_id' => $dto->episodeAssemblyId,
                'publication_request_id' => $dto->publicationRequestId,
            ]);

            $request?->update(['status' => PublicationStatus::PUBLISHED, 'published_at' => now(), 'error_message' => null]);
            DB::afterCommit(static fn() => event(new PostCreated($post)));
        });
    }
}
