<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('форма проекта содержит настройки существующего сериала', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())->get(route('projects.edit', $context['project']))
        ->assertOk()->assertInertia(fn(Assert $page): Assert => $page
        ->component('Projects/Form')
        ->where('series.id', $context['series']->id)
        ->where('series.specification.premise', $context['series']->specification['premise'])
        ->where('audiences.children', 'Children')
        ->where('audioModes.native', 'Speech and sound')
        ->where('contentModes.series', 'Series'));
});

test('форма редактирования возвращает исходный промпт целиком', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create(['prompt' => "Line one\nLine two <script>not executable</script>"]);

    actingAs(workspaceOwner())
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
    actingAs(workspaceOwner())
        ->get(route('projects.edit', 99999))
        ->assertNotFound();
});

test('гость не открывает редактирование', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    get(route('projects.edit', $project))->assertRedirect(route('login'));
});

test('неподтверждённый пользователь не открывает редактирование', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(User::factory()->unverified()->create())
        ->get(route('projects.edit', $project))
        ->assertRedirect(route('verification.notice'));
});

test('оператор получает все каналы и точный выбранный набор', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $attached = Channel::factory()->recycle(workspaceOwner())->for($project)->create(['name' => 'Attached']);
    Channel::factory()->recycle(workspaceOwner())->create(['name' => 'Available']);

    actingAs(serialOperator())->get(route('projects.edit', $project))->assertInertia(fn(Assert $page): Assert => $page
        ->has('channels', 2)->where('selectedChannelIds', [$attached->id])->missing('channels.0.credentials')->missing('channels.0.client_secret'));
});
