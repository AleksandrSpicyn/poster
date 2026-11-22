<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Episode;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Series;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Tests\Support\workspaceOwner;

test('список возвращает проекты со счётчиками без каналов и секретов', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    Prompt::factory()->recycle(workspaceOwner())->for($project)->create(['generated' => true]);
    Prompt::factory()->recycle(workspaceOwner())->for($project)->create(['generated' => false]);
    Channel::factory()->recycle(workspaceOwner())->for($project)->create(['client_secret' => 'private-project-secret']);

    actingAs(workspaceOwner())
        ->get(route('projects.index'))
        ->assertOk()
        ->assertDontSee('private-project-secret')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Projects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $project->id)
            ->where('projects.data.0.prompts_count', 2)
            ->where('projects.data.0.ready_prompts_count', 1)
            ->where('projects.data.0.channels_count', 1)
            ->where('projects.data.0.can_delete', false)
            ->missing('projects.data.0.channels')
            ->where('filters', ['search' => '', 'sort' => 'desc']));
});

test('поиск находит название и промпт без потери фильтра на второй странице', function (): void {
    Project::factory()->recycle(workspaceOwner())->create(['name' => 'Wildlife', 'prompt' => 'A neutral brief']);
    $last = Project::factory()->recycle(workspaceOwner())->create(['name' => 'Another series', 'prompt' => 'WILDLIFE in cities']);
    Project::factory()->recycle(workspaceOwner())->create(['name' => 'Unrelated series', 'prompt' => 'A calm landscape']);

    actingAs(workspaceOwner())
        ->get(route('projects.index', ['search' => 'wildlife', 'perPage' => 1]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('projects.meta.total', 2)
            ->where('projects.data.0.id', $last->id)
            ->where('filters.search', 'wildlife'));

    get(route('projects.index', ['search' => 'wildlife', 'perPage' => 1, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('projects.meta.current_page', 2)
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Wildlife'));
});

test('сортировка стабильна при одинаковой дате', function (): void {
    $first = Project::factory()->recycle(workspaceOwner())->create(['created_at' => '2026-01-01 12:00:00']);
    $last = Project::factory()->recycle(workspaceOwner())->create(['created_at' => '2026-01-01 12:00:00']);

    actingAs(workspaceOwner())
        ->get(route('projects.index', ['sort' => 'asc']))
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('projects.data.0.id', $first->id)
            ->where('projects.data.1.id', $last->id));
});

test('поиск с SQL-фрагментом не расширяет выборку', function (): void {
    Project::factory()->recycle(workspaceOwner())->create();

    actingAs(workspaceOwner())
        ->get(route('projects.index', ['search' => "' OR 1=1 --"]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->has('projects.data', 0));
});

test('список отклоняет некорректные фильтры', function (array $input, string $field): void {
    actingAs(workspaceOwner())
        ->getJson(route('projects.index', $input))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'размер ноль' => [['perPage' => 0], 'perPage'],
    'размер больше лимита' => [['perPage' => 101], 'perPage'],
    'дробная страница' => [['page' => '1.5'], 'page'],
    'нулевая страница' => [['page' => 0], 'page'],
    'неизвестный порядок' => [['sort' => 'random'], 'sort'],
    'поиск массивом' => [['search' => ['invalid']], 'search'],
    'длинный поиск' => [['search' => str_repeat('a', 201)], 'search'],
]);

test('пустые фильтры используют значения по умолчанию', function (): void {
    actingAs(workspaceOwner())
        ->get(route('projects.index', ['search' => '', 'sort' => '', 'page' => '', 'perPage' => '']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('projects.meta.per_page', 10)
            ->where('projects.meta.current_page', 1)
            ->where('filters', ['search' => '', 'sort' => 'desc']));
});

test('гость не получает список проектов', function (): void {
    get(route('projects.index'))->assertRedirect(route('login'));
    getJson(route('projects.index'))->assertUnauthorized();
});

test('неподтверждённый пользователь не получает список проектов', function (): void {
    actingAs(User::factory()->unverified()->create())
        ->get(route('projects.index'))
        ->assertRedirect(route('verification.notice'));
});

test('показывает активные проекты владельца по времени изменения', function (): void {
    $owner = User::factory()->create();
    $old = Project::factory()->create(['owner_id' => $owner->id, 'updated_at' => now()->subDay()]);
    $new = Project::factory()->create(['owner_id' => $owner->id]);
    Project::factory()->create(['owner_id' => $owner->id, 'is_active' => false]);
    Project::factory()->create();

    actingAs($owner);

    get(route('projects.index', ['active' => 1, 'updated' => 1]))
        ->assertInertia(fn(Assert $page): Assert => $page->has('projects.data', 2)
            ->where('projects.data.0.id', $new->id)->where('projects.data.1.id', $old->id)->where('filters.active', true));
});

test('алфавитный порядок и сведения проекта ограничены его владельцем', function (): void {
    $owner = workspaceOwner();
    $b = Project::factory()->create(['owner_id' => $owner->id, 'name' => 'Берег']);
    $a = Project::factory()->create(['owner_id' => $owner->id, 'name' => 'Атлас']);
    $series = Series::factory()->for($a)->create();
    Episode::factory()->for($series)->create();
    $channel = Channel::factory()->for($a)->create(['name' => 'Моя студия', 'client_secret' => 'protected-test-secret']);
    $foreign = Channel::factory()->create();
    $a->channels()->attach($foreign);

    actingAs($owner)
        ->get(route('projects.index', ['alphabetical' => 1, 'info' => $a->id]))
        ->assertOk()
        ->assertDontSee('protected-test-secret')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('projects.data.0.id', $a->id)
            ->where('projects.data.1.id', $b->id)
            ->where('information.owner', $owner->name)
            ->where('information.episodes', 1)
            ->where('information.accepted', 0)
            ->has('information.project.channels', 1)
            ->where('information.project.channels.0.id', $channel->id));

    actingAs(User::factory()->create())
        ->get(route('projects.index', ['info' => $a->id]))
        ->assertNotFound();
});
