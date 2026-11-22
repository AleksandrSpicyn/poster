<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('форма создания передаёт пустой проект и ограничения', function (): void {
    actingAs(User::factory()->create())
        ->get(route('projects.create'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Projects/Form')
            ->where('project', null)
            ->where('limits', ['name' => 255, 'prompt' => 10000]));
});

test('гость не открывает форму создания', function (): void {
    get(route('projects.create'))->assertRedirect(route('login'));
});

test('неподтверждённый пользователь не открывает форму создания', function (): void {
    actingAs(User::factory()->unverified()->create())
        ->get(route('projects.create'))
        ->assertRedirect(route('verification.notice'));
});

test('выбор канала показывает только проекты его владельца', function (): void {
    $owner = User::factory()->create();
    $own = Project::factory()->create(['owner_id' => $owner->id]);
    $other = Project::factory()->create();
    $channel = Channel::factory()->create(['owner_id' => $owner->id, 'project_id' => null]);
    $channel->projects()->attach([$own->id, $other->id]);

    actingAs($owner)
        ->get(route('projects.create'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->has('channels.0.projects', 1)
            ->where('channels.0.projects.0.id', $own->id));
});
