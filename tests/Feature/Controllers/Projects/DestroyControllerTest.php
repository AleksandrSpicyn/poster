<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Post;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use Inertia\Inertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;
use function Tests\Support\workspaceOwner;

test('удаляет пустой проект', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(workspaceOwner())
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    assertDatabaseMissing('projects', ['id' => $project->id]);
    expect(Inertia::getFlashed())->toBe(['success' => 'Project deleted.']);
});

test('проект с генерацией не удаляется', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $prompt = Prompt::factory()->recycle(workspaceOwner())->for($project)->create();

    actingAs(workspaceOwner())
        ->deleteJson(route('projects.destroy', $project))
        ->assertConflict()
        ->assertJsonPath('message', 'This project has generations or publishing channels and cannot be deleted.');

    assertDatabaseHas('projects', ['id' => $project->id]);
    assertDatabaseHas('prompts', ['id' => $prompt->id, 'project_id' => $project->id]);
});

test('каналы и публикации защищают проект от удаления', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $channel = Channel::factory()->recycle(workspaceOwner())->for($project)->create();
    $post = Post::factory()->recycle(workspaceOwner())->for($channel)->create();

    actingAs(workspaceOwner())
        ->deleteJson(route('projects.destroy', $project))
        ->assertConflict();

    assertDatabaseHas('projects', ['id' => $project->id]);
    assertDatabaseHas('channels', ['id' => $channel->id, 'project_id' => $project->id]);
    assertDatabaseHas('posts', ['id' => $post->id, 'channel_id' => $channel->id]);
});

test('браузер получает объяснение отказа без потери данных', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    Channel::factory()->recycle(workspaceOwner())->for($project)->create();

    actingAs(workspaceOwner())
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.show', $project));

    expect(Inertia::getFlashed())->toHaveKey('error');
    assertDatabaseHas('projects', ['id' => $project->id]);
});

test('несуществующий проект возвращает 404', function (): void {
    actingAs(workspaceOwner())
        ->delete(route('projects.destroy', 99999))
        ->assertNotFound();
});

test('гость не удаляет проект', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    delete(route('projects.destroy', $project))->assertRedirect(route('login'));

    assertDatabaseHas('projects', ['id' => $project->id]);
});

test('неподтверждённый пользователь не удаляет проект', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(User::factory()->unverified()->create())
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('verification.notice'));

    assertDatabaseHas('projects', ['id' => $project->id]);
});
