<?php

declare(strict_types=1);

namespace App\Tasks\Posts;

use App\Data\PostData;
use App\Enums\PublicationStatus;
use App\Events\PostCreated;
use App\Models\PublicationRequest;
use App\Repositories\PostRepository;
use App\Repositories\PublicationRequestRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

            if ($request instanceof Model && ($request->channel_id !== $dto->channel->id
                || $request->prompt_id !== $dto->prompt?->id || $request->episode_assembly_id !== $dto->episodeAssemblyId || $request->series_assembly_id !== $dto->seriesAssemblyId)) {
                throw new RuntimeException('The publication receipt does not match its pinned source and channel.');
            }

            $post = $this->posts->createPublished($dto);

            if ($request instanceof PublicationRequest) {
                $this->publications->update($request, ['status' => PublicationStatus::PUBLISHED, 'published_at' => now(), 'error_message' => null]);
            }

            DB::afterCommit(static fn() => event(new PostCreated($post)));
        });
    }
}
