<?php

declare(strict_types=1);

use App\Models\CharacterRevision;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('правка черновика сохраняет описание и увеличивает версию', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->patch(route('characters.revisions.update', [...$context['params'], 'revision' => $context['revision']]), ['description' => 'Mira smiling', 'lock_version' => 0])
        ->assertRedirect();

    expect($context['revision']->refresh()->description)->toBe('Mira smiling');
    expect($context['revision']->lock_version)->toBe(1);
});

test('чужая версия не изменяется', function (): void {
    $context = serialCharacterContext();
    $other = CharacterRevision::factory()->create();
    $original = $other->description;

    actingAs(serialOperator())
        ->patchJson(route('characters.revisions.update', [...$context['params'], 'revision' => $other]), ['description' => 'Changed', 'lock_version' => 0])
        ->assertNotFound();

    expect($other->refresh()->description)->toBe($original);
});

test('утверждённая версия остаётся неизменной', function (): void {
    $context = serialCharacterContext();
    $approved = CharacterRevision::factory()->approved()->for($context['character'])->create();
    $original = $approved->description;

    actingAs(serialOperator())
        ->patchJson(route('characters.revisions.update', [...$context['params'], 'revision' => $approved]), ['description' => 'Changed', 'lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('revision');

    expect($approved->refresh()->description)->toBe($original);
});

test('название версии можно изменить и очистить, а пропуск поля сохраняет его', function (): void {
    $context = serialCharacterContext();
    $revision = $context['revision'];
    $url = route('characters.revisions.update', [...$context['params'], 'revision' => $revision]);
    actingAs(serialOperator());

    foreach ([['title' => 'Новый образ'], [], ['title' => null]] as $index => $input) {
        patch($url, [...$input, 'description' => 'Описание ' . $index, 'lock_version' => $revision->lock_version])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($revision->refresh()->title)->toBe($index === 2 ? null : 'Новый образ');
    }
});
