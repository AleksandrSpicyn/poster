<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Data\GetPostsListData;
use App\Models\Channel;
use App\Models\Post;
use App\Models\PublicationRequest;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Repositories\PublicationRequestRepository;
use App\Tasks\Posts\GetPostsList;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use stdClass;

readonly class GetPostsListAction
{
    public function __construct(private GetPostsList $getList, private ChannelRepository $channels, private PublicationRequestRepository $requests) {}

    /** @return Collection<int, Channel> */
    public function channels(User $owner): Collection
    {
        return $this->channels->forSelection($owner);
    }

    /** @return Collection<int, PublicationRequest> */
    public function recent(User $owner): Collection
    {
        return $this->requests->recentForOwner($owner);
    }

    /** @return LengthAwarePaginator<int, stdClass> */
    public function activity(User $owner, GetPostsListData $filters): LengthAwarePaginator
    {
        return $this->getList->activity($owner, $filters);
    }

    /** @return LengthAwarePaginator<int, Post> */
    public function run(User $owner, GetPostsListData $data): LengthAwarePaginator
    {
        return $this->getList->run($owner, $data);
    }
}
