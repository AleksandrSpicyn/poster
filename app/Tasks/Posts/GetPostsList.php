<?php

declare(strict_types=1);

namespace App\Tasks\Posts;

use App\Data\GetPostsListData;
use App\Enums\ChannelType;
use App\Enums\SortDirection;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Готовит общую выборку публикаций кабинета с фильтром канала и пагинацией.
 * Связь channel загружается для PostResource, id стабилизирует порядок при равных датах.
 */
class GetPostsList
{
    /** @return LengthAwarePaginator<int, Post> */
    public function run(GetPostsListData $dto): LengthAwarePaginator
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
}
