<?php

declare(strict_types=1);

use App\Models\Series;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialOperator;

test('список сериалов ограничен страницей и показывает счётчики', function (): void {
    Series::factory()->count(21)->create();

    actingAs(serialOperator())
        ->get(route('series.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Series/Index')
            ->has('series.data', 20)
            ->where('series.meta.total', 21)
            ->where('series.data.0.characters_count', 0));
});

test('список проверяет номер страницы', function (): void {
    actingAs(serialOperator())
        ->getJson(route('series.index', ['page' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('page');
});

test('гость не видит библиотеку сериалов', function (): void {
    get(route('series.index'))->assertRedirect(route('login'));
});

test('неподтверждённый пользователь не видит сериалы', function (): void {
    actingAs(User::factory()->unverified()->create())
        ->get(route('series.index'))
        ->assertRedirect(route('verification.notice'));
});

test('подтверждённый посторонний пользователь не читает сериалы', function (): void {
    config(['services.admin.email' => 'operator@example.test', 'horizon.allowed_emails' => []]);

    actingAs(User::factory()->create(['email' => 'reader@example.test']))
        ->get(route('series.index'))
        ->assertForbidden();
});
