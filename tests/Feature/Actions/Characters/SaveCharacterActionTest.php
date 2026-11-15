<?php

declare(strict_types=1);

use App\Actions\Characters\SaveCharacterAction;
use App\Data\Characters\CharacterData;
use App\Models\Series;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

test('сохраняет постоянную идентичность персонажа', function (): void {
    $series = Series::factory()->create();
    $action = app(SaveCharacterAction::class);
    $character = $action->run($series, new CharacterData('Mira'));

    $updated = $action->run($series, new CharacterData('Mira Fox', 0), $character);

    expect($updated->id)->toBe($character->id);
    expect($updated->name)->toBe('Mira Fox');
    expect($updated->lock_version)->toBe(1);
    expect($updated->current_revision_id)->toBeNull();
});

test('не перемещает персонажа в другой сериал', function (): void {
    $series = Series::factory()->create();
    $action = app(SaveCharacterAction::class);
    $character = $action->run($series, new CharacterData('Mira'));
    $other = Series::factory()->create();

    expect(fn() => $action->run($other, new CharacterData('Changed', 0), $character))
        ->toThrow(ModelNotFoundException::class);

    expect($character->refresh()->series_id)->toBe($series->id);
    expect($character->name)->toBe('Mira');
});

test('отклоняет устаревшую правку персонажа', function (): void {
    $series = Series::factory()->create();
    $action = app(SaveCharacterAction::class);
    $character = $action->run($series, new CharacterData('Mira'));
    $action->run($series, new CharacterData('New name', 0), $character);

    expect(fn() => $action->run($series, new CharacterData('Old name', 0), $character))
        ->toThrow(ValidationException::class);
});
