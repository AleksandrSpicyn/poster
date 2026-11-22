<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Tests\Support\channelPayload;
use function Tests\Support\projectPayload;
use function Tests\Support\seriesProjectPayload;

test('две студии создают одноимённые сериалы и используют только свои каналы', function (): void {
    Queue::fake();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $legacy = Project::factory()->create(['owner_id' => null]);

    foreach ([$alice, $bob] as $owner) {
        actingAs($owner)->post(route('channels.store'), channelPayload())->assertRedirect();
        $channel = Channel::query()->where('owner_id', $owner->id)->sole();

        post(route('projects.store'), [...seriesProjectPayload(), 'channel_ids' => [$channel->id], 'automation_enabled' => false])
            ->assertSessionHasNoErrors();
    }

    $channelA = Channel::query()->where('owner_id', $alice->id)->sole();
    $projectA = Project::query()->where('owner_id', $alice->id)->sole();
    $channelB = Channel::query()->where('owner_id', $bob->id)->sole();

    get(route('projects.index'))->assertInertia(fn(Assert $page): Assert => $page
        ->where('projects.meta.total', 1)
        ->where('projects.data.0.id', Project::query()->where('owner_id', $bob->id)->sole()->id));
    get(route('projects.show', $projectA))->assertNotFound();
    get(route('projects.show', $legacy))->assertNotFound();
    get(route('channels.edit', $channelA))->assertNotFound();
    get(route('series.index'))->assertInertia(fn(Assert $page): Assert => $page->where('series.meta.total', 1));

    actingAs($alice)->post(route('projects.store'), [...projectPayload(), 'channel_ids' => [$channelA->id]])
        ->assertSessionHasNoErrors();
    expect($channelA->projects()->count())->toBe(2);

    postJson(route('projects.store'), [...seriesProjectPayload(), 'channel_ids' => [$channelB->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('channel_ids');

    assertDatabaseCount('projects', 4);
    assertDatabaseCount('channel_project', 3);
    assertDatabaseCount('series', 2);
    Queue::assertNothingPushed();
});
