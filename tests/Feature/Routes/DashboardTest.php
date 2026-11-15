<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Prompt;
use App\Models\Shot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('гость перенаправляется на вход', function (): void {
    get(route('dashboard'))->assertRedirect(route('login'));
    getJson(route('dashboard'))->assertUnauthorized();
});

test('пользователь видит кабинет', function (): void {
    $user = User::factory()->create();

    actingAs($user);

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Dashboard')
            ->where('stats', ['prompts' => 0, 'pending' => 0, 'ready' => 0, 'posts' => 0])
            ->has('recentPrompts.data', 0)
            ->has('recentPosts.data', 0));
});

test('обзор не раскрывает приватные сериальные дубли', function (): void {
    Prompt::factory()->forShot(Shot::factory()->create())->create(['prompt' => 'SERIAL_PRIVATE_SENTINEL']);

    actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('SERIAL_PRIVATE_SENTINEL')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('stats.prompts', 0)
            ->where('stats.pending', 0)
            ->has('recentPrompts.data', 0));
});

test('неподтверждённый email закрывает кабинет и рабочие списки', function (): void {
    actingAs(User::factory()
        ->unverified()
        ->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
    getJson(route('dashboard'))->assertForbidden();
});

test('обзор считает всю историю и ограничивает последние результаты', function (): void {
    $ready = Prompt::factory()
        ->create(['generated' => true]);
    Prompt::factory()
        ->count(2)
        ->create(['generated' => true]);
    $pending = Prompt::factory()
        ->count(5)
        ->create();
    $posts = Post::factory()
        ->for($ready)
        ->count(7)
        ->create();

    actingAs(User::factory()
        ->create());

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Dashboard')
            ->where('stats', ['prompts' => 8, 'pending' => 5, 'ready' => 3, 'posts' => 7])
            ->has('recentPrompts.data', 6)
            ->where('recentPrompts.data.0.id', $pending->last()?->id)
            ->where('recentPrompts.data.0.stage', 'image')
            ->where('recentPrompts.data.0.project', $pending->last()?->project?->name)
            ->has('recentPosts.data', 5)
            ->where('recentPosts.data.0.id', $posts->last()?->id)
            ->where('recentPosts.data.0.channel', $posts->last()?->channel?->type->value)
            ->missing('recentPosts.data.0.credentials'));
});
