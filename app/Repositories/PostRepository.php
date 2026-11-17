<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\GetPostsListData;
use App\Data\PostData;
use App\Enums\ChannelType;
use App\Enums\SortDirection;
use App\Models\Channel;
use App\Models\Post;
use App\Models\PublicationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/** @extends EloquentRepository<Post> */
readonly class PostRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Post::class;
    }

    /** @return LengthAwarePaginator<int, Post> */
    public function paginate(GetPostsListData $dto): LengthAwarePaginator
    {
        $type = ChannelType::tryFrom(is_string($dto->type) ? $dto->type : '');

        $direction = $dto->sort instanceof SortDirection ? $dto->sort : SortDirection::DESC;

        return Post::query()
            ->with('channel')
            ->when($type, function (Builder $query, ChannelType $type): void {
                $query->whereHas('channel', function (Builder $q) use ($type): void {
                    $q->where('type', $type->value);
                });
            })
            ->orderBy('created_at', $direction->value)
            ->orderBy('id', $direction->value)
            ->paginate($dto->perPage ?? 10, page: $dto->page ?? 1)
            ->withQueryString();
    }

    public function totalCount(): int
    {
        return Post::query()->count();
    }

    /** @return Collection<int, Post> */
    public function recentForDashboard(): Collection
    {
        return Post::query()->with('channel')->latest()->latest('id')->limit(5)->get();
    }

    public function hasReceipt(PublicationRequest $request, string $externalId): bool
    {
        return Post::query()->where('publication_request_id', $request->id)->where('external_id', $externalId)->exists();
    }

    public function receiptUsedElsewhere(Channel $channel, PublicationRequest $request, string $externalId): bool
    {
        return Post::query()->where('channel_id', $channel->id)->where('external_id', $externalId)
            ->where(fn($query) => $query->whereNull('publication_request_id')->orWhere('publication_request_id', '!=', $request->id))
            ->exists();
    }

    public function createPublished(PostData $dto): Post
    {
        return $dto->channel->posts()->create([
            'title' => $dto->title,
            'description' => $dto->description,
            'url' => $dto->url,
            'external_id' => $dto->externalId,
            'prompt_id' => $dto->prompt?->id,
            'episode_assembly_id' => $dto->episodeAssemblyId,
            'series_assembly_id' => $dto->seriesAssemblyId,
            'publication_request_id' => $dto->publicationRequestId,
        ]);
    }
}
