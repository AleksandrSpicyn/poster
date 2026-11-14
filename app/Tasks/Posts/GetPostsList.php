<?php

declare(strict_types=1);

namespace App\Tasks\Posts;

use App\Data\GetPostsListData;
use App\Enums\ChannelType;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GetPostsList
{
    /** @return LengthAwarePaginator<int, Post> */
    public function run(GetPostsListData $dto): LengthAwarePaginator
    {
        $type = ChannelType::tryFrom($dto->type ?? '');

        return Post::query()
            ->with('channel')
            ->when($type, function (Builder $query, ChannelType $type): void {
                $query->whereHas('channel', function (Builder $q) use ($type): void {
                    $q->where('type', $type->value);
                });
            })
            ->orderBy($dto->orderBy ?? 'created_at', $dto->sort === 'asc' ? 'asc' : 'desc')
            ->orderBy('id', $dto->sort === 'asc' ? 'asc' : 'desc')
            ->paginate($dto->perPage)
            ->withQueryString();
    }
}
