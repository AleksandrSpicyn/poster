<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Data\Channels\GetChannelListData;
use App\Models\Channel;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ChannelRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetChannels
{
    public function __construct(private ChannelRepository $channels) {}

    /** @return LengthAwarePaginator<int, Channel> */
    public function run(User $owner, GetChannelListData $filters): LengthAwarePaginator
    {
        return $this->channels->paginate($owner, $filters);
    }

    /** @return Collection<int, Channel> */
    public function selection(User $owner): Collection
    {
        return $this->channels->forSelection($owner);
    }

    /** @return list<int> */
    public function selectedIds(Project $project): array
    {
        return $this->channels->selectedIds($project);
    }

    public function form(Channel $channel): Channel
    {
        return $this->channels->forManagement($channel);
    }
}
