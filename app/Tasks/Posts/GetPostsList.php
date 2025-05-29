<?php

namespace App\Tasks\Posts;

use App\Data\GetPostsListData;
use App\Enums\ChannelType;
use App\Models\Post;
use App\Tasks\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GetPostsList extends Task
{
    public function run(GetPostsListData $dto): LengthAwarePaginator
    {
        $type = ChannelType::tryFrom($dto->type);

        return Post::query()
            ->with('channel')
            ->when($type, function (Builder $query, ChannelType $type): void {
                $query->whereHas('channel', function (Builder $q) use ($type): void {
                    $q->where('type', $type->value);
                });
            })
            ->orderBy($dto->orderBy, $dto->sort)
            ->paginate($dto->perPage)
            ->withQueryString();
    }
}
