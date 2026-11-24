<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\WorkspaceOwnershipRepository;
use Illuminate\Console\Command;

class ReportWorkspaceOwnership extends Command
{
    protected $signature = 'workspace:ownership-report';

    protected $description = 'List unassigned records and channel memberships requiring owner confirmation';

    public function handle(WorkspaceOwnershipRepository $ownership): int
    {
        foreach ($ownership->unassignedProjects() as $project) {
            $this->line("Unassigned project {$project->id}");
        }

        foreach ($ownership->channelsWithProjectOwners() as $channel) {
            if ($channel->owner_id === null) {
                $this->line("Unassigned channel {$channel->id}");
            }

            foreach ($channel->projects as $project) {
                if ($project->owner_id === null || $project->owner_id !== $channel->owner_id) {
                    $this->line("Review channel {$channel->id} / project {$project->id}");
                }
            }
        }

        foreach ($ownership->cardsWithConflictingOwner() as $card) {
            $this->line("Review OpusClip source {$card->opus_source_id} / project {$card->origin_id} / card {$card->id}");
        }

        foreach ($ownership->unassignedChats() as $chat) {
            $this->line("Unassigned notification chat {$chat->id}");
        }

        return self::SUCCESS;
    }
}
