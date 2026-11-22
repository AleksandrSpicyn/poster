<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Episodes\AcceptEpisodeAction;
use App\Actions\Episodes\ApproveEpisodeScriptAction;
use App\Actions\Episodes\SaveEpisodeScriptAction;
use App\Data\Episodes\AcceptEpisodeData;
use App\Data\Episodes\SaveEpisodeScriptData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\EpisodeAssembly;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/** @return array{assembly: EpisodeAssembly, channel: Channel, user: User} */
function acceptedEpisodePublication(): array
{
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $user = serialOperator();
    $revision = app(SaveEpisodeScriptAction::class)->run($episode, SaveEpisodeScriptData::validateAndCreate([
        'script' => [...storyScriptPayload($character), 'public_caption' => 'Who has the key?'], 'lock_version' => 0,
    ]));
    app(ApproveEpisodeScriptAction::class)->run($revision, $user);
    $assembly = EpisodeAssembly::factory()->for($revision)->ready()->create(['duration_ms' => 5000]);
    $assembly->addMedia(UploadedFile::fake()->create('episode.mp4', 10, 'video/mp4'))->toMediaCollection(EpisodeAssembly::VIDEO);
    app(AcceptEpisodeAction::class)->run($assembly, new AcceptEpisodeData(0, 'Key found.', 'Hero holds the key.', '', false), $user);
    $channel = Channel::factory()->recycle(workspaceOwner())->create([
        'project_id' => $episode->series?->project_id,
        'type' => ChannelType::INSTAGRAM,
        'credentials' => ['access_token' => 'test-token', 'channel_id' => 'ig-episode'],
    ]);

    return ['assembly' => $assembly->refresh(), 'channel' => $channel, 'user' => $user];
}
