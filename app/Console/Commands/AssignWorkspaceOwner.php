<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\ModelId;
use App\Repositories\WorkspaceOwnershipRepository;
use DomainException;
use Illuminate\Console\Command;

class AssignWorkspaceOwner extends Command
{
    protected $signature = 'workspace:assign {user : Confirmed user ID} {--project=*} {--channel=*} {--chat=*}';

    protected $description = 'Assign explicitly confirmed legacy projects and channels together';

    public function handle(WorkspaceOwnershipRepository $ownership): int
    {
        try {
            $owner = ModelId::parse($this->argument('user'));
            $projects = array_map(ModelId::parse(...), $this->option('project'));
            $channels = array_map(ModelId::parse(...), $this->option('channel'));
            $chats = array_map(ModelId::parse(...), $this->option('chat'));
        } catch (DomainException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        if ($projects === [] && $channels === [] && $chats === []) {
            $this->error('Specify the confirmed project and channel IDs.');

            return self::FAILURE;
        }

        $ownership->assignConfirmed($owner, $projects, $channels, $chats);

        $this->info('Confirmed ownership assigned.');

        return self::SUCCESS;
    }
}
