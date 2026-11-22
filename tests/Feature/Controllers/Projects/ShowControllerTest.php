<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Enums\ProjectContentMode;
use App\Models\Channel;
use App\Models\Character;
use App\Models\Episode;
use App\Models\OpusClip;
use App\Models\OpusClipMetric;
use App\Models\OpusSource;
use App\Models\Post;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('проект показывает собственных персонажей и эпизоды без отдельной карточки сериала', function (): void {
    $context = serialCharacterContext();
    $context['project']->update(['content_mode' => ProjectContentMode::SERIES]);
    $episode = Episode::factory()->recycle(workspaceOwner())->for($context['series'])->create();
    Character::factory()->create(['name' => 'Foreign private character']);
    Episode::factory()->recycle(workspaceOwner())->create(['title' => 'Foreign private episode']);
    $run = ProductionRun::factory()->recycle(workspaceOwner())->for($context['project'])->for($context['series'])->create();

    actingAs(serialOperator())->get(route('projects.show', $context['project']))
        ->assertOk()->assertDontSee('Foreign private character')->assertDontSee('Foreign private episode')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Projects/Show')
            ->where('project.content_mode', 'series')
            ->where('series.id', $context['series']->id)
            ->where('series.latest_production.id', $run->id)
            ->where('characters.data.0.id', $context['character']->id)
            ->has('characters.data', 1)
            ->where('episodes.0.id', $episode->id));
});

test('читатель проекта не получает приватное рабочее пространство сериала', function (): void {
    $context = serialCharacterContext();
    serialOperator();
    ProductionRun::factory()->recycle(workspaceOwner())->for($context['project'])->for($context['series'])->create(['error_message' => 'Private production error']);

    actingAs(User::factory()->create())->get(route('projects.show', $context['project']))
        ->assertNotFound()->assertDontSee($context['character']->name)->assertDontSee('Private production error');
});

test('страница показывает только историю проекта и безопасные поля каналов', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $older = Prompt::factory()->recycle(workspaceOwner())->for($project)->create(['created_at' => now()->subDay()]);
    $latest = Prompt::factory()->recycle(workspaceOwner())->for($project)->create(['generated' => true]);
    $foreign = Prompt::factory()->recycle(workspaceOwner())->create(['prompt' => 'Foreign generation']);
    $channel = Channel::factory()->recycle(workspaceOwner())->for($project)->create([
        'type' => ChannelType::YOUTUBE,
        'client_secret' => 'private-channel-secret',
        'credentials' => ['access_token' => 'private-access-token'],
    ]);
    Post::factory()->recycle(workspaceOwner())->for($channel)->for($latest)->create();
    Post::factory()->recycle(workspaceOwner())->for($channel)->for($foreign)->create();
    $channel->projects()->attach($foreign->project_id);

    actingAs(workspaceOwner())
        ->get(route('projects.show', ['project' => $project->id, 'perPage' => 1]))
        ->assertOk()
        ->assertDontSee('private-channel-secret')
        ->assertDontSee('private-access-token')
        ->assertDontSee('Foreign generation')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Projects/Show')
            ->where('project.id', $project->id)
            ->where('project.prompts_count', 2)
            ->where('project.ready_prompts_count', 1)
            ->where('project.channels_count', 1)
            ->where('project.can_delete', false)
            ->where('project.channels.0', [
                'id' => $channel->id, 'name' => $channel->name,
                'type' => 'youtube', 'type_label' => 'YouTube', 'posts_count' => 1,
            ])
            ->where('prompts.meta.total', 2)
            ->has('prompts.data', 1)
            ->where('prompts.data.0.id', $latest->id));

    get(route('projects.show', ['project' => $project->id, 'perPage' => 1, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('prompts.data.0.id', $older->id)
            ->where('prompts.meta.current_page', 2));
});

test('пустой проект разрешает удаление', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(workspaceOwner())
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('project.can_delete', true)
            ->where('project.channels_count', 0)
            ->has('project.channels', 0)
            ->has('prompts.data', 0));
});

test('история проекта валидирует пагинацию', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(workspaceOwner())
        ->getJson(route('projects.show', ['project' => $project->id, 'perPage' => 101]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('perPage');
});

test('несуществующий проект возвращает 404', function (): void {
    actingAs(workspaceOwner())->get(route('projects.show', 99999))->assertNotFound();
});

test('гость не видит проект', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    get(route('projects.show', $project))->assertRedirect(route('login'));
});

test('неподтверждённый пользователь не видит проект', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(User::factory()->unverified()->create())
        ->get(route('projects.show', $project))
        ->assertRedirect(route('verification.notice'));
});

test('оператор видит игровые клипы только в их проекте', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $source = OpusSource::factory()->for($project)->create(['game' => 'GTA 6']);
    OpusClip::factory()->for($source, 'source')->create(['title' => 'Game clip']);
    OpusClip::factory()->create(['title' => 'Foreign clip']);

    actingAs(serialOperator())->get(route('projects.show', $project))
        ->assertInertia(fn(Assert $page): Assert => $page->has('opusClips.data', 1)
            ->where('opusClips.data.0.title', 'Game clip')->where('opusClips.data.0.game', 'GTA 6'));

    actingAs(User::factory()->create())->get(route('projects.show', $project))->assertNotFound();
});

test('карточка Opus показывает только клипы своего исходника', function (): void {
    $workspace = Project::factory()->recycle(workspaceOwner())->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $source = OpusSource::factory()->for($workspace)->create();
    $other = OpusSource::factory()->for($workspace)->create();
    $card = Project::factory()->recycle(workspaceOwner())->create(['opus_source_id' => $source->id]);
    OpusClip::factory()->for($source, 'source')->create(['title' => 'Own']);
    OpusClip::factory()->for($other, 'source')->create(['title' => 'Sibling']);

    actingAs(serialOperator())->get(route('projects.show', $card))
        ->assertInertia(fn(Assert $page): Assert => $page->has('opusClips.data', 1)
            ->where('opusClips.data.0.title', 'Own')->where('project.can_delete', false));

    get(route('projects.show', $workspace))
        ->assertInertia(fn(Assert $page): Assert => $page->has('opusClips.data', 2));
});

test('недельный отчёт клипов сопоставляет даты и источники выбранной карточки', function (): void {
    $owner = workspaceOwner();
    $workspace = Project::factory()->create(['owner_id' => $owner->id, 'clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $source = OpusSource::factory()->for($workspace)->create();
    $card = Project::factory()->create(['owner_id' => $owner->id, 'opus_source_id' => $source->id]);
    $clip = OpusClip::factory()->for($source, 'source')->create(['title' => 'Вечерний заезд']);
    $sibling = OpusClip::factory()->for(OpusSource::factory()->for($workspace), 'source')->create();

    foreach ([$clip, $sibling] as $item) {
        foreach (['2025-11-16' => 10, '2025-11-21' => 15, '2025-11-23' => 520] as $day => $views) {
            OpusClipMetric::create(['opus_clip_id' => $item->id, 'platform' => 'youtube', 'media_id' => 'shared', 'account_id' => 'studio', 'observed_on' => $day, 'metrics' => ['views' => $views]]);
        }
    }

    OpusClipMetric::create(['opus_clip_id' => $clip->id, 'platform' => 'instagram', 'media_id' => 'no-baseline', 'observed_on' => '2025-11-23', 'metrics' => ['views' => 9000]]);

    actingAs($owner)
        ->get(route('projects.show', ['project' => $card, 'week' => '2025-11-20']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('clipWeek.from', '2025-11-17')
            ->where('clipWeek.to', '2025-11-23')
            ->where('clipWeek.observedOn', '2025-11-23')
            ->has('clipWeek.clips', 1)
            ->where('clipWeek.clips.0.title', 'Вечерний заезд')
            ->where('clipWeek.clips.0.views', 510));

    get(route('projects.show', ['project' => $card, 'week' => '2025-11-10']))
        ->assertInertia(fn(Assert $page): Assert => $page->has('clipWeek.clips', 0));

    actingAs(User::factory()->create())
        ->get(route('projects.show', ['project' => $card, 'week' => '2025-11-20']))
        ->assertNotFound();
});
