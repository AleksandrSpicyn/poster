<?php

declare(strict_types=1);

use App\Actions\Characters\SaveCharacterRevisionAction;
use App\Data\Characters\CharacterRevisionData;
use App\Enums\RevisionStatus;
use App\Models\Character;
use App\Models\CharacterRevision;
use Illuminate\Validation\ValidationException;

test('создает новую версию без изменения утвержденной внешности', function (): void {
    $character = Character::factory()->create();
    $approved = CharacterRevision::factory()->for($character)->approved()->create(['number' => 1]);

    $draft = app(SaveCharacterRevisionAction::class)->run($character, new CharacterRevisionData('New costume', ['coat' => 'blue']));

    expect($draft->number)->toBe(2);
    expect($draft->status)->toBe(RevisionStatus::DRAFT);
    expect($draft->appearance)->toBe(['coat' => 'blue']);
    expect($approved->refresh()->description)->not->toBe('New costume');
});

test('утвержденная версия не редактируется как черновик', function (): void {
    $revision = CharacterRevision::factory()->approved()->create();
    $character = $revision->character()->firstOrFail();

    expect(fn() => app(SaveCharacterRevisionAction::class)->run($character, new CharacterRevisionData('Changed', lock_version: 0), $revision))
        ->toThrow(ValidationException::class);
});

test('описание без внешности и голоса сохраняет пустые наборы свойств', function (): void {
    $character = Character::factory()->create();
    $data = CharacterRevisionData::validateAndCreate(['description' => 'A new character without detailed appearance or voice.']);

    $revision = app(SaveCharacterRevisionAction::class)->run($character, $data);

    expect($revision->refresh()->appearance)->toBe([]);
    expect($revision->voice)->toBe([]);
    expect($revision->status)->toBe(RevisionStatus::DRAFT);
    expect($character->refresh()->current_revision_id)->toBeNull();
});
