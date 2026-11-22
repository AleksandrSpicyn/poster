<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Chat;
use App\Models\Project;
use Illuminate\Console\Command;

class ReportWorkspaceOwnership extends Command
{
    protected $signature = 'workspace:ownership-report';

    protected $description = 'List unassigned records and channel memberships requiring owner confirmation';

    public function handle(): int
    {
        foreach (Project::query()->whereNull('owner_id')->lazyById() as $project) {
            $this->line("Unassigned project {$project->id}");
        }

        foreach (Channel::query()->with('projects:id,owner_id')->lazyById() as $channel) {
            if ($channel->owner_id === null) {
                $this->line("Unassigned channel {$channel->id}");
            }

            foreach ($channel->projects as $project) {
                if ($project->owner_id === null || $project->owner_id !== $channel->owner_id) {
                    $this->line("Review channel {$channel->id} / project {$project->id}");
                }
            }
        }

        $cards = Project::query()->join('opus_sources', 'opus_sources.id', '=', 'projects.opus_source_id')
            ->join('projects as origins', 'origins.id', '=', 'opus_sources.project_id')
            ->where(fn($query) => $query->whereNull('projects.owner_id')->orWhereNull('origins.owner_id')->orWhereColumn('projects.owner_id', '!=', 'origins.owner_id'))
            ->select('projects.id', 'projects.opus_source_id', 'origins.id as origin_id')
            ->toBase()->lazyById(column: 'projects.id', alias: 'id');

        /** @var object{id: int, opus_source_id: int, origin_id: int} $card */
        foreach ($cards as $card) {
            $this->line("Review OpusClip source {$card->opus_source_id} / project {$card->origin_id} / card {$card->id}");
        }

        foreach (Chat::query()->whereNull('owner_id')->lazyById() as $chat) {
            $this->line("Unassigned notification chat {$chat->id}");
        }

        return self::SUCCESS;
    }
}
