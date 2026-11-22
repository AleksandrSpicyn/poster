<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Chat;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssignWorkspaceOwner extends Command
{
    protected $signature = 'workspace:assign {user : Confirmed user ID} {--project=*} {--channel=*} {--chat=*}';

    protected $description = 'Assign explicitly confirmed legacy projects and channels together';

    public function handle(): int
    {
        $projects = $this->option('project');
        $channels = $this->option('channel');
        $chats = $this->option('chat');

        if ($projects === [] && $channels === [] && $chats === []) {
            $this->error('Specify the confirmed project and channel IDs.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($projects, $channels, $chats): void {
            $owner = User::query()->lockForUpdate()->findOrFail((int) $this->argument('user'));
            $selectedProjects = Project::query()->whereKey($projects)->orderBy('id')->lockForUpdate()->get();
            $selectedChannels = Channel::query()->whereKey($channels)->orderBy('id')->lockForUpdate()->get();

            $selectedChats = Chat::query()->whereKey($chats)->orderBy('id')->lockForUpdate()->get();

            if ($selectedChats->count() !== count($chats) || $selectedProjects->count() !== count($projects) || $selectedChannels->count() !== count($channels)) {
                throw new RuntimeException('An ID is missing or repeated.');
            }

            foreach ([...$selectedProjects, ...$selectedChannels, ...$selectedChats] as $record) {
                if ($record->owner_id !== null && $record->owner_id !== $owner->id) {
                    throw new RuntimeException('This record already belongs to another user.');
                }

                $record->owner_id = $owner->id;
                $record->saveQuietly();
            }

            foreach ($selectedProjects as $project) {
                if ($project->channels()->where(fn($query) => $query->whereNull('owner_id')->orWhere('owner_id', '!=', $owner->id))->exists()) {
                    throw new RuntimeException('Include all connected channels in the confirmed mapping.');
                }
            }

            $sourceIds = OpusSource::query()->whereIn('project_id', $projects)->select('id');

            if (Project::query()->whereIn('opus_source_id', $sourceIds)
                ->where(fn($query) => $query->whereNull('owner_id')->orWhere('owner_id', '!=', $owner->id))->exists()
                || OpusSource::query()->whereKey($selectedProjects->pluck('opus_source_id')->filter()->values()->all())
                    ->whereHas('project', fn($query) => $query->whereNull('owner_id')->orWhere('owner_id', '!=', $owner->id))->exists()) {
                throw new RuntimeException('Include the OpusClip source project and all imported project cards in the mapping.');
            }

            foreach ($selectedChannels as $channel) {
                if ($channel->projects()->where(fn($query) => $query->whereNull('owner_id')->orWhere('owner_id', '!=', $owner->id))->exists()) {
                    throw new RuntimeException('Include all connected projects in the confirmed mapping.');
                }
            }
        });

        $this->info('Confirmed ownership assigned.');

        return self::SUCCESS;
    }
}
