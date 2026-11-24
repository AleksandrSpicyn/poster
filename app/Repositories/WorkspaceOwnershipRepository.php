<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Channel;
use App\Models\Chat;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use RuntimeException;

final class WorkspaceOwnershipRepository
{
    /**
     * @param array<int, int> $projects
     * @param array<int, int> $channels
     * @param array<int, int> $chats
     */
    public function assignConfirmed(int $ownerId, array $projects, array $channels, array $chats): void
    {
        DB::transaction(function () use ($ownerId, $projects, $channels, $chats): void {
            $owner = User::query()->lockForUpdate()->findOrFail($ownerId);
            $selectedProjects = Project::query()->whereKey($projects)->orderBy('id')->lockForUpdate()->get();
            $selectedChannels = Channel::query()->whereKey($channels)->orderBy('id')->lockForUpdate()->get();

            $selectedChats = Chat::query()->whereKey($chats)->orderBy('id')->lockForUpdate()->get();

            if ($selectedChats->count() !== count($chats) || $selectedProjects->count() !== count($projects) || $selectedChannels->count() !== count($channels)) {
                throw new RuntimeException('An ID is missing or repeated.');
            }

            foreach ([
                ...$selectedProjects,
                ...$selectedChannels,
                ...$selectedChats,
            ] as $record) {
                if ($record->owner_id !== null && $record->owner_id !== $owner->id) {
                    throw new RuntimeException('This record already belongs to another user.');
                }

                $record->owner_id = $owner->id;
                $record->saveQuietly();
            }

            if (Project::query()->whereKey($projects)->whereHas('channels', fn($query) => $query
                ->where(fn($related) => $related->whereNull('owner_id')->orWhere('owner_id', '!=', $owner->id)))->exists()) {
                throw new RuntimeException('Include all connected channels in the confirmed mapping.');
            }

            $sourceIds = OpusSource::query()->whereIn('project_id', $projects)->select('id');

            if (Project::query()->whereIn('opus_source_id', $sourceIds)
                ->where(fn($query) => $query->whereNull('owner_id')->orWhere('owner_id', '!=', $owner->id))->exists()
                || OpusSource::query()->whereKey($selectedProjects->pluck('opus_source_id')->filter()->values()->all())
                    ->whereHas('project', fn($query) => $query->whereNull('owner_id')->orWhere('owner_id', '!=', $owner->id))->exists()) {
                throw new RuntimeException('Include the OpusClip source project and all imported project cards in the mapping.');
            }

            if (Channel::query()->whereKey($channels)->whereHas('projects', fn($query) => $query
                ->where(fn($related) => $related->whereNull('owner_id')->orWhere('owner_id', '!=', $owner->id)))->exists()) {
                throw new RuntimeException('Include all connected projects in the confirmed mapping.');
            }
        });
    }

    /** @return LazyCollection<int, Project> */
    public function unassignedProjects(): LazyCollection
    {
        return Project::query()->whereNull('owner_id')->lazyById();
    }

    /** @return LazyCollection<int, Channel> */
    public function channelsWithProjectOwners(): LazyCollection
    {
        return Channel::query()->with('projects:id,owner_id')->lazyById();
    }

    /** @return LazyCollection<int, object{id: int, opus_source_id: int, origin_id: int}> */
    public function cardsWithConflictingOwner(): LazyCollection
    {
        /** @var LazyCollection<int, object{id: int, opus_source_id: int, origin_id: int}> */
        return Project::query()->join('opus_sources', 'opus_sources.id', '=', 'projects.opus_source_id')
            ->join('projects as origins', 'origins.id', '=', 'opus_sources.project_id')
            ->where(fn($query) => $query->whereNull('projects.owner_id')->orWhereNull('origins.owner_id')->orWhereColumn('projects.owner_id', '!=', 'origins.owner_id'))
            ->select('projects.id', 'projects.opus_source_id', 'origins.id as origin_id')
            ->toBase()->lazyById(column: 'projects.id', alias: 'id');
    }

    /** @return LazyCollection<int, Chat> */
    public function unassignedChats(): LazyCollection
    {
        return Chat::query()->whereNull('owner_id')->lazyById();
    }
}
