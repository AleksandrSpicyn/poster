<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Chat;
use App\Models\OpusSource;
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

        foreach (OpusSource::query()->with('project')->lazyById() as $source) {
            foreach (Project::query()->where('opus_source_id', $source->id)->lazyById() as $card) {
                if ($card->owner_id === null || $card->owner_id !== $source->project?->owner_id) {
                    $this->line("Review OpusClip source {$source->id} / project {$source->project_id} / card {$card->id}");
                }
            }
        }

        foreach (Chat::query()->whereNull('owner_id')->lazyById() as $chat) {
            $this->line("Unassigned notification chat {$chat->id}");
        }

        return self::SUCCESS;
    }
}
