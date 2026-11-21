<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Project;

use function Pest\Laravel\assertDatabaseCount;
use function Tests\Support\consoleCommand;

test('привязывает существующий проект и аккаунты его каналов', function (): void {
    $project = Project::factory()->create();
    $youtube = Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['channel_id' => 'fixture-channel']]);
    $instagram = Channel::factory()->for($project)->create(['type' => ChannelType::INSTAGRAM]);

    consoleCommand('opus:configure', ['project' => $project->id, '--instagram-account' => 'fixture_creator'])
        ->assertSuccessful();
    consoleCommand('opus:configure', ['project' => $project->id])->assertSuccessful();

    expect($project->refresh()->clipping_workspace)->toBe(Project::OPUS_CLIP_WORKSPACE)
        ->and($youtube->refresh()->external_account)->toBe('fixture-channel')
        ->and($instagram->refresh()->external_account)->toBe('fixture_creator');
    assertDatabaseCount('projects', 1);
    assertDatabaseCount('channels', 2);
});

test('откатывает настройку при несовпадении владельца канала', function (): void {
    $project = Project::factory()->create();
    $youtube = Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE]);
    $instagram = Channel::factory()->for($project)->create(['type' => ChannelType::INSTAGRAM, 'credentials' => ['account_name' => 'fixture_original']]);

    consoleCommand('opus:configure', ['project' => $project->id, '--youtube-account' => 'fixture-channel', '--instagram-account' => 'fixture_other'])
        ->assertFailed();

    expect($project->refresh()->clipping_workspace)->toBeNull()
        ->and($youtube->refresh()->external_account)->toBeNull()
        ->and($instagram->refresh()->credentials)->toBe(['account_name' => 'fixture_original']);
});

test('общий с другим проектом канал не становится игровым', function (): void {
    $project = Project::factory()->create();
    $channel = Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE]);
    $channel->projects()->attach(Project::factory()->create());
    Channel::factory()->for($project)->create(['type' => ChannelType::INSTAGRAM]);

    consoleCommand('opus:configure', ['project' => $project->id, '--youtube-account' => 'fixture-channel', '--instagram-account' => 'fixture_creator'])
        ->assertFailed();

    expect($project->refresh()->clipping_workspace)->toBeNull()
        ->and($channel->refresh()->external_account)->toBeNull();
});
