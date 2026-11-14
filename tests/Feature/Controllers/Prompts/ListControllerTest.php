<?php

declare(strict_types=1);

use App\Models\Prompt;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('список идей возвращает страницы и данные проекта', function (): void {
    $first = Prompt::factory()->create(['created_at' => now()->subDay()]);
    $last = Prompt::factory()->create();

    actingAs(User::factory()->create())
        ->get(route('prompts', ['sort' => 'desc', 'perPage' => 1]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Prompts')
            ->has('prompts.data', 1)
            ->where('prompts.data.0.id', $last->id)
            ->where('prompts.data.0.project', $last->project?->name)
            ->where('prompts.meta.total', 2));
    get(route('prompts', ['sort' => 'desc', 'perPage' => 1, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('prompts.data.0.id', $first->id));
});

test('явный null размера страницы сохраняет документированный default', function (): void {
    actingAs(User::factory()->create())
        ->get(route('prompts', ['perPage' => '']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('prompts.meta.per_page', 10));
});

test('неподтверждённый email закрывает кабинет и рабочие списки', function (): void {
    actingAs(User::factory()
        ->unverified()
        ->create())
        ->get(route('prompts'))
        ->assertRedirect(route('verification.notice'));
    getJson(route('prompts'))->assertForbidden();
});

test('гость не видит рабочие списки', function (): void {
    get(route('prompts'))->assertRedirect(route('login'));
    getJson(route('prompts'))->assertUnauthorized();
});

test('недокументированный столбец сортировки не попадает в SQL', function (): void {
    actingAs(User::factory()->create())
        ->get(route('prompts', ['orderBy' => 'unknown_column']))
        ->assertOk();
});

test('списки отклоняют некорректные фильтры', function (array $input, string $field): void {
    actingAs(User::factory()->create())
        ->getJson(route('prompts', $input))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'размер страницы' => [['perPage' => 0], 'perPage'],
    'порядок' => [['sort' => 'invalid'], 'sort'],
]);
