<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Post;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('список публикаций фильтруется по каналу и сортируется', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);
    $first = Post::factory()
        ->for($channel)
        ->create(['created_at' => now()->subDay()]);
    $last = Post::factory()
        ->for($channel)
        ->create();

    Post::factory()
        ->for(Channel::factory()->state(['type' => ChannelType::VK]))
        ->create();

    actingAs(User::factory()->create())
        ->get(route('posts', ['type' => 'youtube', 'sort' => 'asc']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Posts')
            ->has('posts.data', 2)
            ->where('posts.data.0.id', $first->id)
            ->where('posts.data.1.id', $last->id)
            ->where('posts.data.0.channel', 'youtube')
            ->where('filters.type', 'youtube'));
});

test('удалённый канал не ломает историю публикаций', function (): void {
    $post = Post::factory()->create();

    $post
        ->channel()
        ->delete();

    actingAs(User::factory()->create())
        ->get(route('posts'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('posts.data.0.id', $post->id)
            ->where('posts.data.0.channel', null));
});

test('неподтверждённый email закрывает кабинет и рабочие списки', function (): void {
    actingAs(User::factory()
        ->unverified()
        ->create())
        ->get(route('posts'))
        ->assertRedirect(route('verification.notice'));
    getJson(route('posts'))->assertForbidden();
});

test('гость не видит рабочие списки', function (): void {
    get(route('posts'))->assertRedirect(route('login'));
    getJson(route('posts'))->assertUnauthorized();
});

test('недокументированный столбец сортировки не попадает в SQL', function (): void {
    actingAs(User::factory()->create())
        ->get(route('posts', ['orderBy' => 'unknown_column']))
        ->assertOk();
});

test('списки отклоняют некорректные фильтры', function (array $input, string $field): void {
    actingAs(User::factory()->create())
        ->getJson(route('posts', $input))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'канал' => [['type' => 'unknown'], 'type'],
    'размер страницы' => [['perPage' => 101], 'perPage'],
    'порядок' => [['sort' => 'invalid'], 'sort'],
    'нулевая страница' => [['page' => 0], 'page'],
    'дробная страница' => [['page' => '1.5'], 'page'],
    'дробный размер' => [['perPage' => '1.5'], 'perPage'],
]);

test('пустые фильтры сохраняют размер страницы и порядок по умолчанию', function (): void {
    Post::factory()->create(['created_at' => now()->subDay()]);
    $last = Post::factory()->create();

    actingAs(User::factory()->create())
        ->get(route('posts', ['sort' => '', 'perPage' => '', 'page' => '']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('posts.data.0.id', $last->id)
            ->where('posts.meta.per_page', 10)
            ->where('posts.meta.current_page', 1)
            ->where('filters.sort', null));
});

test('отсутствующие фильтры не добавляются в ответ', function (): void {
    actingAs(User::factory()->create())
        ->get(route('posts'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('filters', []));
});

test('публикации используют валидированные страницу и размер', function (): void {
    $first = Post::factory()->create(['created_at' => now()->subDay()]);
    Post::factory()->create();

    actingAs(User::factory()->create())
        ->get(route('posts', ['perPage' => 1, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $first->id)
            ->where('posts.meta.current_page', 2)
            ->where('posts.meta.per_page', 1));
});
