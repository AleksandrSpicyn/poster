<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Publications\PublicationReference;
use App\Data\GetPostsListData;
use App\Data\PostData;
use App\Enums\ChannelType;
use App\Enums\SortDirection;
use App\Models\Channel;
use App\Models\Post;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

/** @extends EloquentRepository<Post> */
readonly class PostRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Post::class;
    }

    /** @return LengthAwarePaginator<int, Post> */
    public function paginate(User $owner, GetPostsListData $dto): LengthAwarePaginator
    {
        $type = ChannelType::tryFrom(is_string($dto->type) ? $dto->type : '');

        $direction = $dto->sort instanceof SortDirection ? $dto->sort : SortDirection::DESC;

        return $this->owned($owner)
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

    /** @return LazyCollection<int, Post> */
    public function forMetricCollection(User $owner, Channel $channel): LazyCollection
    {
        return $this->owned($owner)->where('channel_id', $channel->id)->whereNotNull('url')
            ->with(['prompt:id,project_id', 'seriesAssembly.series', 'episodeAssembly.episodeRevision.episode.series'])->lazyById(100);
    }

    public function totalCount(User $owner): int
    {
        return $this->owned($owner)->count();
    }

    /** @return Collection<int, Post> */
    public function recentForDashboard(User $owner): Collection
    {
        return $this->owned($owner)->with('channel')->latest()->latest('id')->limit(5)->get();
    }

    /** @return Builder<Post> */
    private function owned(User $owner): Builder
    {
        return Post::query()
            ->where(fn(Builder $query) => $query->whereDoesntHave('channel')->orWhereRelation('channel', 'owner_id', $owner->id))
            ->where(fn(Builder $query) => $query->whereRelation('prompt.project', 'owner_id', $owner->id)
                ->orWhereRelation('episodeAssembly.episodeRevision.episode.series.project', 'owner_id', $owner->id)
                ->orWhereRelation('seriesAssembly.series.project', 'owner_id', $owner->id));
    }

    public function hasReceipt(PublicationRequest $request, PublicationReference $reference): bool
    {
        $post = Post::query()->where('publication_request_id', $request->id)->first();

        if ($post === null || $reference->platform !== ChannelType::INSTAGRAM) {
            return $post !== null && $post->external_id === $reference->value;
        }

        return is_string($post->url) && preg_match('~\Ahttps://(?:www\.)?instagram\.com/(?:p|reel)/([A-Za-z0-9_-]+)/?(?:[?#].*)?\z~', $post->url, $match)
            && $reference->value === 'https://www.instagram.com/reel/' . $match[1] . '/';
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
