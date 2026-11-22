<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
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
            ->where('project.blocking_runs_count', 0)
            ->where('project.can_delete', true));
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

test('следующий запуск учитывает пропущенный час при переходе на летнее время', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create([
        'schedule_enabled' => true, 'schedule_timezone' => 'America/New_York', 'schedule_time' => '02:30',
    ]);
    config(['production.schedule_enabled' => true]);
    Carbon::setTestNow('2026-03-08T05:30:00Z');

    try {
        actingAs(workspaceOwner())->get(route('projects.edit', $project))
            ->assertInertia(fn(Assert $page): Assert => $page
                ->where('scheduleSettings.next', '2026-03-09T02:30:00-04:00')
                ->where('scheduleSettings.time', '02:30')
                ->where('scheduleSettings.configured', true));
    } finally {
        Carbon::setTestNow();
    }
});

test('роль владельца в настройках проекта определяется адресом администратора', function (bool $admin): void {
    $owner = workspaceOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $other = Project::factory()->create();
    config(['services.admin.email' => $admin ? $owner->email : 'other-admin@example.test']);

    actingAs($owner)->get(route('projects.edit', $project))
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('auth.user.email', $owner->email)
            ->where('auth.can_view_logs', $admin));
    actingAs($owner)->get(route('projects.edit', $other))->assertNotFound();
})->with([true, false]);
