<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('новая версия создаётся черновиком и не меняет текущую внешность', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->post(route('characters.revisions.store', $context['params']), ['description' => 'Mira in a blue coat', 'appearance' => ['coat' => 'blue'], 'voice' => ['tone' => 'calm']])
        ->assertRedirect();

    $revision = $context['character']->revisions()->orderByDesc('number')->firstOrFail();
    expect($revision->description)->toBe('Mira in a blue coat');
    expect($revision->appearance)->toBe(['coat' => 'blue']);
    expect($revision->status->value)->toBe('draft');
    expect($context['character']->refresh()->current_revision_id)->toBeNull();
});

test('пустое описание не создаёт версию', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->postJson(route('characters.revisions.store', $context['params']), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('description');

    expect($context['character']->revisions()->count())->toBe(1);
});
