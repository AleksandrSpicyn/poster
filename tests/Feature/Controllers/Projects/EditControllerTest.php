<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('форма редактирования возвращает исходный промпт целиком', function (): void {
    $project = Project::factory()->create(['prompt' => "Line one\nLine two <script>not executable</script>"]);

    actingAs(User::factory()->create())
        ->get(route('projects.edit', $project))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Projects/Form')
            ->where('project.id', $project->id)
            ->where('project.prompt', $project->prompt)
            ->missing('project.channels')
            ->missing('project.can_delete'));
});

test('несуществующий проект не открывается для редактирования', function (): void {
    actingAs(User::factory()->create())
        ->get(route('projects.edit', 99999))
        ->assertNotFound();
});

test('гость не открывает редактирование', function (): void {
    $project = Project::factory()->create();

    get(route('projects.edit', $project))->assertRedirect(route('login'));
});

test('неподтверждённый пользователь не открывает редактирование', function (): void {
    $project = Project::factory()->create();

    actingAs(User::factory()->unverified()->create())
        ->get(route('projects.edit', $project))
        ->assertRedirect(route('verification.notice'));
});
