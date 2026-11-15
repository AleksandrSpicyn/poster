<?php

declare(strict_types=1);

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
