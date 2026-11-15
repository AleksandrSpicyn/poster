<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Series;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;

test('рабочая область отдаёт сценарий и скрывает ключи каналов и payload задания', function (): void {
    $context = episodeHttpFixture();
    $series = $context['episode']->series()->firstOrFail();
    Channel::factory()->create(['project_id' => $series->project_id, 'client_secret' => 'hidden-oauth-secret', 'credentials' => ['access_token' => 'hidden-access-token']]);
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
    $other = Series::factory()->create();

    actingAs($context['user'])
        ->get(route('episodes.show', ['series' => $other, 'episode' => $context['episode']]))
        ->assertNotFound();
});

test('подтверждённый посторонний пользователь не читает сценарий и дубли', function (): void {
    $context = episodeHttpFixture();
    config(['services.admin.email' => 'operator@example.test', 'horizon.allowed_emails' => []]);

    actingAs(User::factory()->create(['email' => 'reader@example.test']))
        ->get(route('episodes.show', ['series' => $context['episode']->series_id, 'episode' => $context['episode']]))
        ->assertForbidden();
});
