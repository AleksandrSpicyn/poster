<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use Inertia\Inertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\patch;
use function Tests\Support\invalidProjectInputs;
use function Tests\Support\projectPayload;

test('изменение сохраняет тему без потери истории и каналов', function (): void {
    $project = Project::factory()->create();
    $prompt = Prompt::factory()->for($project)->create();
    $channel = Channel::factory()->for($project)->create();

    actingAs(User::factory()->create())
        ->patch(route('projects.update', $project), projectPayload())
        ->assertRedirect(route('projects.show', $project));

    assertDatabaseHas('projects', ['id' => $project->id, ...projectPayload()]);
    assertDatabaseHas('prompts', ['id' => $prompt->id, 'project_id' => $project->id, 'prompt' => $prompt->prompt]);
    assertDatabaseHas('channels', ['id' => $channel->id, 'project_id' => $project->id]);
    expect(Inertia::getFlashed())->toBe(['success' => 'Project updated.']);
});

test('ошибка изменения не меняет проект', function (array $invalid, string $field): void {
    $project = Project::factory()->create(['name' => 'Original', 'prompt' => 'Keep the original topic']);

    actingAs(User::factory()->create())
        ->patchJson(route('projects.update', $project), [...projectPayload(), ...$invalid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original', 'prompt' => 'Keep the original topic']);
})->with(invalidProjectInputs());

test('Inertia PATCH возвращает 303', function (): void {
    $project = Project::factory()->create();

    actingAs(User::factory()->create())
        ->patch(route('projects.update', $project), projectPayload(), ['X-Inertia' => 'true'])
        ->assertStatus(303)
        ->assertRedirect(route('projects.show', $project));
});

test('отсутствующий проект не обновляется', function (): void {
    actingAs(User::factory()->create())
        ->patch(route('projects.update', 99999), projectPayload())
        ->assertNotFound();
});

test('гость не меняет проект', function (): void {
    $project = Project::factory()->create(['name' => 'Original']);

    patch(route('projects.update', $project), projectPayload())->assertRedirect(route('login'));

    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original']);
});

test('неподтверждённый пользователь не меняет проект', function (): void {
    $project = Project::factory()->create(['name' => 'Original']);

    actingAs(User::factory()->unverified()->create())
        ->patch(route('projects.update', $project), projectPayload())
        ->assertRedirect(route('verification.notice'));

    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original']);
});
