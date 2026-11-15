<?php

declare(strict_types=1);

use App\Models\Project;

use function Pest\Laravel\actingAs;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\serialSeriesPayload;

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
    $other = Project::factory()->create();

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
