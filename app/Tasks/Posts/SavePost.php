<?php

declare(strict_types=1);

namespace App\Tasks\Posts;

use App\Data\PostData;
use App\Enums\PublicationStatus;
use App\Events\PostCreated;
use App\Models\PublicationRequest;
use App\Repositories\PostRepository;
use App\Repositories\PublicationRequestRepository;
use Illuminate\Support\Facades\DB;

/**
 * Записывает результат публикации через связь канала и отправляет PostCreated
 * для запуска уведомлений; внешний upload выполняет Publisher.
 */
class SavePost
{
    public function __construct(
        private readonly PublicationRequestRepository $publications,
        private readonly PostRepository $posts,
    ) {}

    public function run(PostData $dto): void
    {
        DB::transaction(function () use ($dto): void {
            $request = $dto->publicationRequestId === null ? null : $this->publications->findLockedOrFail($dto->publicationRequestId);

            if ($request?->status === PublicationStatus::PUBLISHED) {
                return;
            }

            $post = $this->posts->createPublished($dto);

            if ($request instanceof PublicationRequest) {
                $this->publications->update($request, ['status' => PublicationStatus::PUBLISHED, 'published_at' => now(), 'error_message' => null]);
            }

            DB::afterCommit(static fn() => event(new PostCreated($post)));
        });
    }
}
