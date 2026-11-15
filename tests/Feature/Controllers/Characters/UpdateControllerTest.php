<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('переименование сохраняет версии внешности', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->patch(route('characters.update', $context['params']), ['name' => 'Mira', 'lock_version' => 0])
        ->assertRedirect();

    expect($context['character']->refresh()->name)->toBe('Mira');
    expect($context['character']->lock_version)->toBe(1);
    expect($context['character']->revisions()->count())->toBe(1);
});

test('устаревшая правка не переименовывает персонажа', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->patchJson(route('characters.update', $context['params']), ['name' => 'Mira', 'lock_version' => 9])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lock_version');

    expect($context['character']->refresh()->name)->not->toBe('Mira');
});
