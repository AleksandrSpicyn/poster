<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\Project;
use Illuminate\Support\Facades\Bus;

use function Pest\Laravel\actingAs;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\serialSeriesPayload;
use function Tests\Support\workspaceOwner;

test('правка паспорта увеличивает версию и сохраняет персонажей', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->patch(route('series.update', $context['params']), [...serialSeriesPayload(), 'lock_version' => 0])
        ->assertRedirect();

    expect($context['series']->refresh()->name)->toBe('Moonlight café');
    expect($context['series']->specification_version)->toBe(2);
    expect($context['series']->characters()->count())->toBe(1);
});

test('устаревшая правка не меняет паспорт', function (): void {
    $context = serialCharacterContext();
    $context['series']->update(['lock_version' => 2]);

    actingAs(serialOperator())
        ->patchJson(route('series.update', $context['params']), [...serialSeriesPayload(), 'lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lock_version');

    expect($context['series']->refresh()->lock_version)->toBe(2);
    expect($context['series']->name)->not->toBe('Moonlight café');
});

test('правка не переносит сериал в чужой проект', function (): void {
    $context = serialCharacterContext();
    $other = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(serialOperator())
        ->patchJson(route('series.update', ['project' => $other, 'series' => $context['series']]), [...serialSeriesPayload(), 'lock_version' => 0])
        ->assertNotFound();

    expect($context['series']->refresh()->project_id)->toBe($context['project']->id);
});

test('смена режима звука сохраняется как новая версия паспорта', function (): void {
    $context = serialCharacterContext();
    $payload = serialSeriesPayload();
    $payload['specification']['audio_mode'] = 'silent';

    actingAs(serialOperator())
        ->patch(route('series.update', $context['params']), [...$payload, 'lock_version' => 0])
        ->assertRedirect();

    expect($context['series']->refresh()->specification['audio_mode'])->toBe('silent');
    expect($context['series']->specification_version)->toBe(2);
    expect($context['series']->lock_version)->toBe(1);
});

test('сохраняет независимые настройки сборки и отправки при редактировании', function (): void {
    Bus::fake();
    $context = serialCharacterContext();
    $series = $context['series'];
    $payload = [...serialSeriesPayload(), 'automation_enabled' => true, 'auto_publish' => false, 'lock_version' => 0];

    actingAs(serialOperator())->patch(route('series.update', $context['params']), $payload)->assertRedirect()->assertSessionHasNoErrors();

    expect($series->refresh()->automation_enabled)->toBeTrue()->and($series->auto_publish)->toBeFalse();

    actingAs(serialOperator())->patch(route('series.update', $context['params']), [
        ...serialSeriesPayload(), 'automation_enabled' => false, 'lock_version' => $series->lock_version,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($series->refresh()->automation_enabled)->toBeFalse()->and($series->auto_publish)->toBeFalse();
});

test('проверяет принадлежность главного героя и сохраняет голос следующих эпизодов', function (): void {
    $context = serialCharacterContext();
    $context['revision']->update(['status' => 'approved']);
    $context['character']->update(['current_revision_id' => $context['revision']->id]);
    $foreign = Character::factory()->create();
    $payload = [...serialSeriesPayload(), 'lock_version' => 0];
    $cast = ['lead_character_id' => $context['character']->id, 'voice_description' => 'Мягкий голос', 'use_series_voice' => true];

    actingAs(serialOperator())->patchJson(route('series.update', $context['params']), [
        ...$payload, 'cast_settings' => [...$cast, 'lead_character_id' => $foreign->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('cast_settings.lead_character_id');
    expect($context['series']->refresh()->cast_settings)->toBeNull();
    actingAs(serialOperator())->patch(route('series.update', $context['params']), [...$payload, 'cast_settings' => $cast])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($context['series']->refresh()->cast_settings?->toArray())->toBe($cast);
});
