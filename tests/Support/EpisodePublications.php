<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Episodes\ApproveEpisodeScriptAction;
use App\Actions\Episodes\SaveEpisodeScriptAction;
use App\Data\Episodes\AcceptEpisodeData;
use App\Data\Episodes\SaveEpisodeScriptData;
use App\Enums\ChannelType;
use App\Enums\UpscaleOperationStatus;
use App\Enums\UpscaleProvider;
use App\Models\Channel;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Tasks\Episodes\AcceptEpisode;

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
    $assembly->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);
    app(AcceptEpisode::class)->run($assembly, new AcceptEpisodeData(0, 'Key found.', 'Hero holds the key.', '', false), $user);
    readyPublicationExport($assembly, $user);
    $channel = Channel::factory()->recycle(workspaceOwner())->create([
        'project_id' => $episode->series?->project_id,
        'type' => ChannelType::INSTAGRAM,
        'credentials' => ['access_token' => 'test-token', 'channel_id' => 'ig-episode'],
    ]);

    return ['assembly' => $assembly->refresh(), 'channel' => $channel, 'user' => $user];
}

function readyPublicationExport(EpisodeAssembly|SeriesAssembly|Prompt $source, User $owner): void
{
    $operation = $source->upscales()->first() ?? $source->upscales()->create([
        'initiator_id' => $owner->id, 'attempt' => 1, 'operation_key' => hash('sha256', $source->getMorphClass() . $source->id),
        'provider' => UpscaleProvider::FAL, 'status' => UpscaleOperationStatus::PENDING,
        'source_snapshot' => [], 'profile_snapshot' => ['export' => '1080p'], 'currency' => 'USD', 'estimated_cost_e8' => 0,
    ]);
    $checksum = hash_file('sha256', base_path('tests/Fixtures/shot.mp4'));
    $file = $source->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()
        ->withCustomProperties(['checksum' => $checksum])->toMediaCollection(EpisodeAssembly::UPSCALED, 'local');
    $operation->forceFill(['status' => UpscaleOperationStatus::READY, 'result_media_id' => $file->id, 'result_sha256' => $checksum])->save();
}
