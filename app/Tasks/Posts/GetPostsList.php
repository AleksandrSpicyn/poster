<?php

declare(strict_types=1);

namespace App\Tasks\Posts;

use App\Data\GetPostsListData;
use App\Models\Post;
use App\Models\User;
use App\Repositories\PostRepository;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Готовит общую выборку публикаций кабинета с фильтром канала и пагинацией.
 * Связь channel загружается для PostResource, id стабилизирует порядок при равных датах.
 */
class GetPostsList
{
    public function __construct(private readonly PostRepository $posts) {}

    /** @return LengthAwarePaginator<int, Post> */
    public function run(User $owner, GetPostsListData $dto): LengthAwarePaginator
    {
        return $this->posts->paginate($owner, $dto);
    }
}
