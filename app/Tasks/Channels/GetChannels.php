<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Models\Channel;
use App\Models\Project;
use App\Repositories\ChannelRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetChannels
{
    public function __construct(private ChannelRepository $channels) {}

    /** @return LengthAwarePaginator<int, Channel> */
    public function run(): LengthAwarePaginator
    {
        return $this->channels->paginate();
    }

    /** @return Collection<int, Channel> */
    public function selection(): Collection
    {
        return $this->channels->forSelection();
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
