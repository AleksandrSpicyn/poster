<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Episode;
use App\Models\OpusClip;
use App\Models\OpusSource;
use App\Models\Post;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Series;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Tests\Support\workspaceOwner;

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
            ->where('auth.can_view_logs', false)
            ->where('auth.can_view_queues', false)
            ->where('stats', ['prompts' => 0, 'pending' => 0, 'ready' => 0, 'posts' => 0])
            ->has('recentPrompts.data', 0)
            ->has('recentPosts.data', 0));
});

test('обзор не раскрывает приватные сериальные дубли', function (): void {
    Prompt::factory()->recycle(workspaceOwner())->forShot(Shot::factory()->create())->create(['prompt' => 'SERIAL_PRIVATE_SENTINEL']);

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
    $ready = Prompt::factory()->recycle(workspaceOwner())
        ->create(['generated' => true]);
    Prompt::factory()->recycle(workspaceOwner())
        ->count(2)
        ->create(['generated' => true]);
    $pending = Prompt::factory()->recycle(workspaceOwner())
        ->count(5)
        ->create();
    $posts = Post::factory()->recycle(workspaceOwner())
        ->for($ready)
        ->count(7)
        ->create();

    actingAs(workspaceOwner());

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

test('считает одну публикацию из двух источников и оставляет пропуск на графике', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $otherGame = Project::factory()->create(['owner_id' => $owner->id]);
    $channel = Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE, 'external_account' => 'game-account']);
    $channel->projects()->attach($otherGame);
    $foreign = Project::factory()->create();
    Channel::factory()->for($foreign)->create(['type' => ChannelType::YOUTUBE, 'external_account' => 'other-account']);

    foreach ([$project, $otherGame, $foreign] as $game) {
        $clip = OpusClip::factory()->for(OpusSource::factory()->for($game), 'source')->create();

        foreach ([7 => 100, 5 => 145, 4 => 142] as $days => $value) {
            $clip->latestMetrics()->create([
                'platform' => 'youtube', 'media_id' => 'abc12345678', 'observed_on' => now('UTC')->subDays($days)->toDateString(),
                'metrics' => ['views' => $value, 'likes' => 0],
            ]);
        }
    }

    actingAs($owner);

    get(route('dashboard'))
        ->assertInertia(fn(Assert $page): Assert => $page->has('analytics.publications', 1)
            ->where('analytics.summaries.views', 42)
            ->where('analytics.summaries.likes', 0)
            ->where('analytics.summaries.shares', null)
            ->where('analytics.summaries.comments', null)
            ->where('analytics.change', 42)->where('analytics.publications.0.intervals.0.change', 45)
            ->where('analytics.daily.' . now('UTC')->subDays(5)->toDateString(), null)
            ->where('analytics.daily.' . now('UTC')->subDays(4)->toDateString(), -3));
    get(route('dashboard', ['metric' => 'likes']))
        ->assertInertia(fn(Assert $page): Assert => $page->where('analytics.change', 0));
    get(route('dashboard', ['metric' => 'shares']))
        ->assertInertia(fn(Assert $page): Assert => $page->where('analytics.change', null));
    get(route('dashboard', ['platform' => 'tiktok']))
        ->assertInertia(fn(Assert $page): Assert => $page->has('analytics.publications', 0));
    Http::assertNothingSent();
});

test('ограничивает производство выбранными проектом и каналом', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $another = Project::factory()->create(['owner_id' => $owner->id]);
    $channel = Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE]);
    $anotherChannel = Channel::factory()->for($another)->create(['type' => ChannelType::INSTAGRAM]);
    $series = Series::factory()->for($project)->create();
    Episode::factory()->for($series)->create();
    $prompt = Prompt::factory()->for($project)->create();
    $post = Post::factory()->for($channel)->for($prompt)->create();
    Post::factory()->for($anotherChannel)->for(Prompt::factory()->for($another))->create();
    actingAs($owner);

    get(route('dashboard', ['project' => $project->id, 'platform' => 'youtube']))
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('analytics.production.projects', 1)
            ->where('analytics.production.openEpisodes', 1)
            ->where('analytics.production.latestGeneration', $prompt->prompt)
            ->where('analytics.production.latestPublication', $post->title));

    get(route('dashboard', ['project' => $project->id, 'exclude_channels' => [$channel->id]]))
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('analytics.production.projects', 0)
            ->where('analytics.production.openEpisodes', 0)
            ->where('analytics.production.latestGeneration', null)
            ->where('analytics.production.latestPublication', null));
});
