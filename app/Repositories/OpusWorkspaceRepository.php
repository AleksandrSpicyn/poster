<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ChannelType;
use App\Enums\ProductionStatus;
use App\Models\Channel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class OpusWorkspaceRepository
{
    public function project(): ?Project
    {
        return Project::query()->where('clipping_workspace', Project::OPUS_CLIP_WORKSPACE)->first();
    }

    public function configure(int $projectId, ?string $youtubeAccount, ?string $instagramAccount, ?string $tiktokAccount = null): void
    {
        DB::transaction(function () use ($projectId, $youtubeAccount, $instagramAccount, $tiktokAccount): void {
            $project = Project::query()->lockForUpdate()->find($projectId);

            if (! $project instanceof Project) {
                throw new RuntimeException('Poster project not found.');
            }

            if (Project::query()->where('clipping_workspace', Project::OPUS_CLIP_WORKSPACE)->whereKeyNot($projectId)->exists()) {
                throw new RuntimeException('The OpusClip workspace is already assigned to another Poster project.');
            }

            if ($project->productionRuns()->whereIn('status', ProductionStatus::blockingValues())->exists()) {
                throw new RuntimeException('Resolve the existing production run before configuring clipping.');
            }

            $channels = $project->channels()->orderBy('channels.id')->lockForUpdate()->get();

            $this->bindAccount($channels, $projectId, ChannelType::YOUTUBE, $youtubeAccount);
            $this->bindAccount($channels, $projectId, ChannelType::INSTAGRAM, $instagramAccount);
            $this->bindAccount($channels, $projectId, ChannelType::TIKTOK, $tiktokAccount);

            $project->clipping_workspace = Project::OPUS_CLIP_WORKSPACE;
            $project->save();
        }, attempts: 3);
    }

    /** @param Collection<int, Channel> $channels */
    private function bindAccount(Collection $channels, int $projectId, ChannelType $type, ?string $requested): void
    {
        $matches = $channels->filter(fn(Channel $channel): bool => $channel->type === $type);

        if ($type === ChannelType::TIKTOK && $matches->isEmpty() && $requested === null) {
            return;
        }

        if ($matches->count() !== 1) {
            throw new RuntimeException('Attach exactly one ' . $type->label() . ' channel to the project.');
        }

        $channel = $matches->sole();

        if ($channel->projects()->whereKeyNot($projectId)->exists()) {
            throw new RuntimeException('Attach a dedicated channel for each clipping account.');
        }

        $credential = $type === ChannelType::INSTAGRAM ? 'account_name' : 'channel_id';
        $connected = $channel->credentials[$credential] ?? null;
        $account = $requested ?? $channel->external_account ?? $connected;

        if (! is_string($account) || trim($account) === '' || mb_strlen($account) > 255) {
            throw new RuntimeException('Supply the external account for the ' . $type->label() . ' channel or connect it in Poster.');
        }

        if (($channel->external_account !== null && $channel->external_account !== $account)
            || ($connected !== null && $connected !== $account)) {
            throw new RuntimeException('The supplied account differs from the existing channel identity.');
        }

        $channel->external_account = $account;
        $channel->save();
    }

    /** @return Collection<int, Channel> */
    public function channels(Project $project): Collection
    {
        return $project->channels()->whereNotNull('external_account')->where('channels.is_active', true)->get();
    }
}
