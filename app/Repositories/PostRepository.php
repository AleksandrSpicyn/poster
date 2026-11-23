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
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use stdClass;

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

    /** @return LengthAwarePaginator<int, stdClass> */
    public function activity(User $owner, GetPostsListData $filters): LengthAwarePaginator
    {
        $published = $this->owned($owner)
            ->leftJoin('channels', 'channels.id', '=', 'posts.channel_id')
            ->leftJoin('publication_requests as receipt', 'receipt.id', '=', 'posts.publication_request_id')
            ->select(['posts.id', 'posts.title', 'posts.url', 'posts.created_at', 'channels.type as platform', 'channels.name as channel_name', 'posts.channel_id'])
            ->selectRaw("COALESCE(receipt.published_at, posts.created_at) as event_at, 'published' as status, 'post' as kind");

        $requests = PublicationRequest::query()
            ->join('channels', 'channels.id', '=', 'publication_requests.channel_id')
            ->where('publication_requests.created_by', $owner->id)
            ->where('channels.owner_id', $owner->id)
            ->where(fn(Builder $query) => $query->whereRelation('prompt.project', 'owner_id', $owner->id)
                ->orWhereRelation('assembly.episodeRevision.episode.series.project', 'owner_id', $owner->id)
                ->orWhereRelation('seriesAssembly.series.project', 'owner_id', $owner->id))
            ->whereNotExists(fn(QueryBuilder $query) => $query->selectRaw('1')->from('posts')->whereColumn('posts.publication_request_id', 'publication_requests.id'))
            ->select(['publication_requests.id', 'publication_requests.payload->title as title'])
            ->selectRaw('NULL as url')
            ->addSelect(['publication_requests.created_at', 'channels.type as platform', 'channels.name as channel_name', 'publication_requests.channel_id'])
            ->selectRaw("COALESCE(publication_requests.published_at, publication_requests.scheduled_at, publication_requests.created_at) as event_at, publication_requests.status, 'request' as kind");

        $type = is_string($filters->type) ? ChannelType::tryFrom($filters->type) : null;
        $order = $filters->sort instanceof SortDirection ? $filters->sort : SortDirection::DESC;

        return DB::query()->fromSub($published->toBase()->unionAll($requests->toBase()), 'activity')
            ->when($filters->days > 0, fn($query) => $query->where('created_at', '>=', now()->subDays($filters->days)))
            ->when($type, fn($query) => $query->where('platform', $type?->value))
            ->when(filled($filters->search), fn($query) => $query->whereLike('title', '%' . $filters->search . '%'))
            ->orderBy('created_at', $order->value)->orderBy('kind')->orderBy('id', $order->value)
            ->paginate($filters->perPage ?? 10, page: $filters->page ?? 1)->withQueryString();
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
