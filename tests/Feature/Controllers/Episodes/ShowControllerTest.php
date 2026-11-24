<?php

declare(strict_types=1);

use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\Channel;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\workspaceOwner;

test('рабочая область отдаёт сценарий и скрывает ключи каналов и payload задания', function (): void {
    $context = episodeHttpFixture();
    $context['shot']->update(['title' => 'Указатель', 'plan_type' => 'Крупный план']);
    $series = $context['episode']->series()->firstOrFail();
    Channel::factory()->recycle(workspaceOwner())->create(['project_id' => $series->project_id, 'client_secret' => 'hidden-oauth-secret', 'credentials' => ['access_token' => 'hidden-access-token']]);
    $context['episode']->update(['script_request_payload' => ['private' => 'hidden-script-request']]);
    $context['revision']->update(['context_snapshot' => array_replace($context['revision']->context_snapshot, [
        'private' => 'hidden-context-internal',
        'continuity' => ['confirmed_events' => 'The hidden key was found.', 'character_state' => 'The hero is waiting.', 'open_threads' => 'Who owns the key?', 'scene_closed' => false],
    ])]);

    actingAs($context['user'])
        ->get(route('episodes.show', ['series' => $series, 'episode' => $context['episode']]))
        ->assertOk()
        ->assertDontSee('hidden-oauth-secret')
        ->assertDontSee('hidden-access-token')
        ->assertDontSee('hidden-script-request')
        ->assertDontSee('hidden-context-internal')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Episodes/Show')
            ->where('episode.id', $context['episode']->id)
            ->where('episode.current_revision.scenes.0.shots.0.id', $context['shot']->id)
            ->where('episode.current_revision.scenes.0.shots.0.title', 'Указатель')
            ->where('screenplay.scenes.0.shots.0.plan_type', 'Крупный план')
            ->where('episode.current_revision.context.confirmed_events', 'The hidden key was found.')
            ->where('episode.current_revision.context.scene_closed', false)
            ->where('screenplay.scenes.0.shots.0.action', $context['shot']->action));
});

test('рабочая область скрыта в другом сериале', function (): void {
    $context = episodeHttpFixture();
    $other = Series::factory()->recycle(workspaceOwner())->create();

    actingAs($context['user'])
        ->get(route('episodes.show', ['series' => $other, 'episode' => $context['episode']]))
        ->assertNotFound();
});

test('подтверждённый посторонний пользователь не читает сценарий и дубли', function (): void {
    $context = episodeHttpFixture();
    config(['services.admin.email' => 'operator@example.test', 'horizon.allowed_emails' => []]);

    actingAs(User::factory()->create(['email' => 'reader@example.test']))
        ->get(route('episodes.show', ['series' => $context['episode']->series_id, 'episode' => $context['episode']]))
        ->assertNotFound();
});

test('дубль позволяет повторную загрузку только при сохранённом результате провайдера', function (bool $hasResult): void {
    $context = episodeHttpFixture();
    $episode = $context['episode'];
    $take = Prompt::factory()->for($context['shot'])->create(['project_id' => $episode->series()->firstOrFail()->project_id]);
    $take->generationOperations()->create([
        'stage' => GenerationOperationStage::DOWNLOAD, 'status' => GenerationOperationStatus::FAILED,
        'request_key' => 'display-download', 'idempotency_key' => hash('sha256', 'display-download'),
        'request_snapshot' => [], 'result_snapshot' => $hasResult ? ['video_url' => 'https://media.example.test/private-source'] : [],
    ]);

    actingAs($context['user'])
        ->get(route('episodes.show', ['series' => $episode->series_id, 'episode' => $episode]))
        ->assertOk()
        ->assertDontSee('private-source')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('episode.current_revision.scenes.0.shots.0.takes.0.operations.0.can_retry_download', $hasResult)
            ->where('episode.current_revision.scenes.0.shots.0.takes.0.operations.0.stage_label', 'Загрузка файла'));
})->with([true, false]);

test('возвращает выбранный дубль и интервал без внутренних полей манифеста', function (): void {
    $context = episodeHttpFixture();
    $shot = $context['shot'];
    $series = $context['episode']->series()->firstOrFail();
    $take = Prompt::factory()->for($shot)->create(['project_id' => $series->project_id]);

    foreach (range(2, 12) as $number) {
        Prompt::factory()->for($shot)->create(['project_id' => $series->project_id, 'take_number' => $number]);
    }

    EpisodeAssembly::factory()->for($context['revision'], 'episodeRevision')->ready()->create(['manifest' => [
        'shots' => [['shot_id' => $shot->id, 'prompt_id' => $take->id, 'media_id' => 234, 'checksum' => 'private-test-checksum', 'trim_start_ms' => 501, 'trim_end_ms' => 1234]],
    ]]);

    actingAs($context['user'])
        ->get(route('episodes.show', ['series' => $series, 'episode' => $context['episode']]))
        ->assertOk()
        ->assertDontSee('private-test-checksum')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('episode.current_revision.scenes.0.shots.0.takes.10.id', $take->id)
            ->where('assemblies.0.selection.0.shot_id', $shot->id)
            ->where('assemblies.0.selection.0.prompt_id', $take->id)
            ->where('assemblies.0.selection.0.trim_start_ms', 501)
            ->where('assemblies.0.selection.0.trim_end_ms', 1234)
            ->missing('assemblies.0.selection.0.media_id'));
});

test('выбор получателей исключает отключённые и чужие каналы даже при старой связи', function (): void {
    $context = episodeHttpFixture();
    $series = $context['episode']->series()->firstOrFail();
    $project = $series->project()->firstOrFail();
    $ready = Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE, 'is_active' => true, 'credentials' => ['access_token' => 'local-only']]);
    $foreign = Channel::factory()->create(['type' => ChannelType::YOUTUBE, 'is_active' => true, 'credentials' => ['access_token' => 'foreign-only']]);
    $project->channels()->attach($foreign);
    Channel::factory()->for($project)->create(['type' => ChannelType::INSTAGRAM, 'is_active' => true, 'credentials' => null]);
    Channel::factory()->for($project)->create(['type' => ChannelType::TIKTOK, 'is_active' => true, 'credentials' => ['access_token' => 'read-only', 'scope' => 'video.list']]);

    actingAs($context['user'])
        ->get(route('episodes.show', ['series' => $series, 'episode' => $context['episode']]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->has('channels', 1)
            ->where('channels.0.id', $ready->id));
});

test('показывает исходную принятую сборку сохранённой памяти', function (): void {
    $context = episodeHttpFixture();
    $episode = $context['episode'];
    $previous = Episode::factory()->create(['series_id' => $episode->series_id, 'number' => 2]);
    $revision = EpisodeRevision::factory()->for($previous)->create();
    $original = EpisodeAssembly::factory()->for($revision, 'episodeRevision')->create(['status' => AssemblyStatus::ACCEPTED, 'accepted_at' => now()]);
    $replacement = EpisodeAssembly::factory()->for($revision, 'episodeRevision')->create(['status' => AssemblyStatus::ACCEPTED, 'accepted_at' => now()]);
    $previous->update(['accepted_assembly_id' => $replacement->id]);
    $episode->update(['number' => 3, 'predecessor_episode_id' => $previous->id]);
    $context['revision']->update(['source_assembly_id' => $original->id]);
    $route = route('episodes.show', [$episode->series_id, $episode]);

    actingAs($context['user'])->get($route)
        ->assertInertia(fn(Assert $page): Assert => $page->where('memoryAssembly.id', $original->id)
            ->where('sourceAssembly.id', $replacement->id)->where('predecessorNumber', 2));

    $foreign = EpisodeAssembly::factory()->create(['status' => AssemblyStatus::ACCEPTED, 'accepted_at' => now()]);
    $context['revision']->update(['source_assembly_id' => $foreign->id]);

    actingAs($context['user'])->get($route)
        ->assertInertia(fn(Assert $page): Assert => $page->where('memoryAssembly', null));
});

test('обновление баланса редактора не загружает сцены и дубли', function (): void {
    $context = episodeHttpFixture();
    $episode = $context['episode'];
    $response = actingAs($context['user'])->get(route('episodes.show', [$episode->series_id, $episode]));

    DB::enableQueryLog();

    try {
        $response->assertInertia(fn(Assert $page): Assert => $page
            ->reloadOnly('balance', fn(Assert $partial): Assert => $partial
                ->has('balance.available')
                ->missing('episode')));
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect(collect($queries)->pluck('query')->implode(' '))->not->toMatch('/from [`"](scenes|shots|prompts)[`"]/i');
});

test('история хранит заявки одного канала по всем версиям в порядке создания', function (): void {
    $context = episodeHttpFixture();
    $episode = $context['episode'];
    $channel = Channel::factory()->recycle($context['user'])->create();
    $assemblyIds = EpisodeAssembly::factory()
        ->for($context['revision'], 'episodeRevision')
        ->count(21)
        ->create()
        ->modelKeys();
    $createdAt = now()->startOfSecond();
    $requestIds = collect([0, 20, 1])->map(function (int $index) use ($assemblyIds, $createdAt, $channel, $context): int {
        $request = new PublicationRequest([
            'episode_assembly_id' => $assemblyIds[$index],
            'channel_id' => $channel->id,
            'created_by' => $context['user']->id,
            'payload' => ['title' => 'Проверка истории'],
        ]);
        $request->created_at = $index === 1 ? $createdAt->copy()->subDay() : $createdAt;
        $request->save();

        return $request->id;
    })->all();

    actingAs($context['user'])
        ->get(route('episodes.show', [$episode->series_id, $episode]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->has('assemblies', 21)
            ->has('publications', 3)
            ->where('publications.0.id', $requestIds[1])
            ->where('publications.1.id', $requestIds[0])
            ->where('publications.1.created_at', $createdAt->toISOString())
            ->where('publications.2.id', $requestIds[2]));
});
