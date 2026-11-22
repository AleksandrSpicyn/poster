<?php

declare(strict_types=1);

use App\Events\WorkspaceUpdated;
use App\Models\Channel;
use App\Models\Post;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;
use function Pest\Laravel\patchJson;

test('владелец архивирует и восстанавливает проект вместе с историей', function (bool $active): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id, 'is_active' => $active]);
    $prompt = Prompt::factory()->for($project)->create();
    $channel = Channel::factory()->for($project)->create();
    $post = Post::factory()->for($channel)->for($prompt)->create();
    $url = route('projects.archive', $project);

    actingAs($owner)->patch($url, ['archived' => true])->assertRedirect(route('projects.index', ['archived' => 1]));
    expect($project->refresh()->archived_at)->not->toBeNull();
    $project->forceFill(['archived_at' => now()->subDay()])->save();
    $timestamp = $project->refresh()->archived_at;

    patch($url, ['archived' => true])->assertRedirect();
    expect($project->refresh()->archived_at)->toEqual($timestamp)
        ->and($project->is_active)->toBe($active)
        ->and($project->channels()->whereKey($channel->id)->exists())->toBeTrue()
        ->and($post->refresh()->prompt_id)->toBe($prompt->id);

    patch($url, ['archived' => false])->assertRedirect(route('projects.index'));
    expect($project->refresh()->archived_at)->toBeNull()->and($project->is_active)->toBe($active);
})->with([true, false]);

test('архив проекта принимает только владельца и явное состояние', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $url = route('projects.archive', $project);

    patch($url, ['archived' => true])->assertRedirect(route('login'));
    actingAs(User::factory()->create())->patch($url, ['archived' => true])->assertNotFound();
    actingAs($owner)->patchJson($url, ['archived' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('archived');
    patchJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('archived');
    expect($project->refresh()->archived_at)->toBeNull();
});

test('архив оповещает только студию владельца при изменении состояния', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    Event::fake([WorkspaceUpdated::class]);
    $url = route('projects.archive', $project);

    actingAs($owner)->patch($url, ['archived' => true])->assertRedirect();
    patch($url, ['archived' => true])->assertRedirect();

    Event::assertDispatched(WorkspaceUpdated::class, fn(WorkspaceUpdated $event): bool => $event->broadcastOn()[0]->name === 'private-workspace.' . $owner->id
        && $event->broadcastWith()['resource'] === 'Project' && $event->broadcastWith()['id'] === $project->id);
    Event::assertDispatchedTimes(WorkspaceUpdated::class, 1);
});
