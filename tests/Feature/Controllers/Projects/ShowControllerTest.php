<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Enums\ProjectContentMode;
use App\Models\Channel;
use App\Models\Character;
use App\Models\Episode;
use App\Models\OpusClip;
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

test('проект показывает собственных персонажей и эпизоды без отдельной карточки сериала', function (): void {
    $context = serialCharacterContext();
    $context['project']->update(['content_mode' => ProjectContentMode::SERIES]);
    $episode = Episode::factory()->for($context['series'])->create();
    Character::factory()->create(['name' => 'Foreign private character']);
    Episode::factory()->create(['title' => 'Foreign private episode']);
    $run = ProductionRun::factory()->for($context['project'])->for($context['series'])->create();

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
    ProductionRun::factory()->for($context['project'])->for($context['series'])->create(['error_message' => 'Private production error']);

    actingAs(User::factory()->create())->get(route('projects.show', $context['project']))
        ->assertOk()->assertDontSee($context['character']->name)->assertDontSee('Private production error')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('series', null)->where('characters', null)->where('project.latest_production', null)->has('episodes', 0));
});

test('страница показывает только историю проекта и безопасные поля каналов', function (): void {
    $project = Project::factory()->create();
    $older = Prompt::factory()->for($project)->create(['created_at' => now()->subDay()]);
    $latest = Prompt::factory()->for($project)->create(['generated' => true]);
    $foreign = Prompt::factory()->create(['prompt' => 'Foreign generation']);
    $channel = Channel::factory()->for($project)->create([
        'type' => ChannelType::YOUTUBE,
        'client_secret' => 'private-channel-secret',
        'credentials' => ['access_token' => 'private-access-token'],
    ]);
    Post::factory()->for($channel)->for($latest)->create();
    Post::factory()->for($channel)->for($foreign)->create();
    $channel->projects()->attach($foreign->project_id);

    actingAs(User::factory()->create())
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
                'type' => 'youtube', 'type_label' => 'Youtube', 'posts_count' => 1,
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
    $project = Project::factory()->create();

    actingAs(User::factory()->create())
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('project.can_delete', true)
            ->where('project.channels_count', 0)
            ->has('project.channels', 0)
            ->has('prompts.data', 0));
});

test('история проекта валидирует пагинацию', function (): void {
    $project = Project::factory()->create();

    actingAs(User::factory()->create())
        ->getJson(route('projects.show', ['project' => $project->id, 'perPage' => 101]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('perPage');
});

test('несуществующий проект возвращает 404', function (): void {
    actingAs(User::factory()->create())->get(route('projects.show', 99999))->assertNotFound();
});

test('гость не видит проект', function (): void {
    $project = Project::factory()->create();

    get(route('projects.show', $project))->assertRedirect(route('login'));
});

test('неподтверждённый пользователь не видит проект', function (): void {
    $project = Project::factory()->create();

    actingAs(User::factory()->unverified()->create())
        ->get(route('projects.show', $project))
        ->assertRedirect(route('verification.notice'));
});

test('оператор видит игровые клипы только в их проекте', function (): void {
    $project = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $source = OpusSource::factory()->for($project)->create(['game' => 'GTA 6']);
    OpusClip::factory()->for($source, 'source')->create(['title' => 'Game clip']);
    OpusClip::factory()->create(['title' => 'Foreign clip']);

    actingAs(serialOperator())->get(route('projects.show', $project))
        ->assertInertia(fn(Assert $page): Assert => $page->has('opusClips.data', 1)
            ->where('opusClips.data.0.title', 'Game clip')->where('opusClips.data.0.game', 'GTA 6'));

    actingAs(User::factory()->create())->get(route('projects.show', $project))
        ->assertInertia(fn(Assert $page): Assert => $page->where('opusClips', null));
});

test('карточка Opus показывает только клипы своего исходника', function (): void {
    $workspace = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $source = OpusSource::factory()->for($workspace)->create();
    $other = OpusSource::factory()->for($workspace)->create();
    $card = Project::factory()->create(['opus_source_id' => $source->id]);
    OpusClip::factory()->for($source, 'source')->create(['title' => 'Own']);
    OpusClip::factory()->for($other, 'source')->create(['title' => 'Sibling']);

    actingAs(serialOperator())->get(route('projects.show', $card))
        ->assertInertia(fn(Assert $page): Assert => $page->has('opusClips.data', 1)
            ->where('opusClips.data.0.title', 'Own')->where('project.can_delete', false));

    get(route('projects.show', $workspace))
        ->assertInertia(fn(Assert $page): Assert => $page->has('opusClips.data', 2));
});
