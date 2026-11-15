<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('архив сохраняет сериал и все версии персонажей', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->post(route('series.archive', $context['params']), ['lock_version' => 0])
        ->assertRedirect();

    expect($context['series']->refresh()->archived_at)->not->toBeNull();
    expect($context['character']->fresh())->not->toBeNull();
    expect($context['revision']->fresh())->not->toBeNull();
});

test('архив требует актуальную версию', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->postJson(route('series.archive', $context['params']), ['lock_version' => 10])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lock_version');

    expect($context['series']->refresh()->archived_at)->toBeNull();
});

test('гость не архивирует сериал', function (): void {
    $context = serialCharacterContext();

    post(route('series.archive', $context['params']), ['lock_version' => 0])->assertRedirect(route('login'));

    expect($context['series']->refresh()->archived_at)->toBeNull();
});
