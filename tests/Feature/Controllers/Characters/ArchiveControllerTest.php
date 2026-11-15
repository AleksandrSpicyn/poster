<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('архивирование сохраняет версии персонажа', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->post(route('characters.archive', $context['params']), ['lock_version' => 0])
        ->assertRedirect();

    expect($context['character']->refresh()->archived_at)->not->toBeNull();
    expect($context['revision']->fresh())->not->toBeNull();
});

test('архивирование требует актуальную версию', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->postJson(route('characters.archive', $context['params']), ['lock_version' => 4])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lock_version');

    expect($context['character']->refresh()->archived_at)->toBeNull();
});
