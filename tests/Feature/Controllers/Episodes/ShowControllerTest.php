<?php

declare(strict_types=1);

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\Channel;
use App\Models\Prompt;
use App\Models\Series;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\workspaceOwner;

test('рабочая область отдаёт сценарий и скрывает ключи каналов и payload задания', function (): void {
    $context = episodeHttpFixture();
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
