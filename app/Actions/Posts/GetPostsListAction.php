<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Data\GetPostsListData;
use App\Models\Post;
use App\Tasks\Posts\GetPostsList;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetPostsListAction
{
    public function __construct(private GetPostsList $getList) {}

    /** @return LengthAwarePaginator<int, Post> */
    public function run(GetPostsListData $data): LengthAwarePaginator
    {
        return $this->getList->run($data);
    }
}
