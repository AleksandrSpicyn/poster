<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Inertia\Inertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\post;
use function Tests\Support\invalidProjectInputs;
use function Tests\Support\projectPayload;

test('создание сохраняет проект и не запускает генерацию', function (): void {
    $user = User::factory()->create();
    Process::fake();
    Http::fake();

    $response = actingAs($user)->post(route('projects.store'), [
        ...projectPayload(), 'id' => 99999, 'credentials' => ['secret' => 'ignored'],
    ]);

    $project = Project::query()->firstOrFail();
    $response->assertRedirect(route('projects.show', $project));
    assertDatabaseCount('projects', 1);
    assertDatabaseHas('projects', projectPayload());
    expect($project->id)->not->toBe(99999);
    expect(Inertia::getFlashed())->toBe(['success' => 'Project created.']);
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('создание отклоняет некорректные поля', function (array $invalid, string $field): void {
    actingAs(User::factory()->create())
        ->postJson(route('projects.store'), [...projectPayload(), ...$invalid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    assertDatabaseCount('projects', 0);
})->with(invalidProjectInputs());

test('создание принимает предельные длины', function (): void {
    actingAs(User::factory()->create())
        ->post(route('projects.store'), ['name' => str_repeat('Ж', 255), 'prompt' => str_repeat('я', 10000)])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    assertDatabaseHas('projects', ['name' => str_repeat('Ж', 255), 'prompt' => str_repeat('я', 10000)]);
});

test('ошибка формы сохраняет проект несозданным', function (): void {
    actingAs(User::factory()->create())
        ->from(route('projects.create'))
        ->post(route('projects.store'), ['name' => 'Series'])
        ->assertRedirect(route('projects.create'))
        ->assertSessionHasErrors('prompt');

    assertDatabaseCount('projects', 0);
});

test('гость не создаёт проект', function (): void {
    post(route('projects.store'), projectPayload())->assertRedirect(route('login'));

    assertDatabaseCount('projects', 0);
});

test('неподтверждённый пользователь не создаёт проект', function (): void {
    actingAs(User::factory()->unverified()->create())
        ->post(route('projects.store'), projectPayload())
        ->assertRedirect(route('verification.notice'));

    assertDatabaseCount('projects', 0);
});
