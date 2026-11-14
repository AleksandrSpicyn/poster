<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Post;
use App\Models\Prompt;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('гость не видит рабочие списки', function (string $route): void {
    get(route($route))->assertRedirect(route('login'));
})->with(['posts', 'prompts']);

test('список публикаций фильтруется по каналу и сортируется', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);
    $first = Post::factory()->for($channel)->create(['created_at' => now()->subDay()]);
    $last = Post::factory()->for($channel)->create();
    Post::factory()->for(Channel::factory()->state(['type' => ChannelType::VK]))->create();

    actingAs(User::factory()->create())->get(route('posts', ['type' => 'youtube', 'sort' => 'asc']))
        ->assertOk()->assertInertia(fn(Assert $page): Assert => $page
        ->component('Posts')->has('posts.data', 2)
        ->where('posts.data.0.id', $first->id)->where('posts.data.1.id', $last->id)
        ->where('posts.data.0.channel', 'youtube')->where('filters.type', 'youtube'));
});

test('список идей возвращает страницы и данные проекта', function (): void {
    $first = Prompt::factory()->create(['created_at' => now()->subDay()]);
    $last = Prompt::factory()->create();

    actingAs(User::factory()->create())->get(route('prompts', ['sort' => 'desc', 'perPage' => 1]))
        ->assertOk()->assertInertia(fn(Assert $page): Assert => $page
        ->component('Prompts')->has('prompts.data', 1)
        ->where('prompts.data.0.id', $last->id)->where('prompts.data.0.project', $last->project?->name)
        ->where('prompts.meta.total', 2));
    get(route('prompts', ['sort' => 'desc', 'perPage' => 1, 'page' => 2]))
        ->assertOk()->assertInertia(fn(Assert $page): Assert => $page->where('prompts.data.0.id', $first->id));
});

test('списки отклоняют некорректные фильтры', function (string $route, array $input, string $field): void {
    actingAs(User::factory()->create())->getJson(route($route, $input))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['posts', ['type' => 'unknown'], 'type'],
    ['posts', ['perPage' => 101], 'perPage'],
    ['posts', ['sort' => 'invalid'], 'sort'],
    ['prompts', ['perPage' => 0], 'perPage'],
    ['prompts', ['sort' => 'invalid'], 'sort'],
]);
