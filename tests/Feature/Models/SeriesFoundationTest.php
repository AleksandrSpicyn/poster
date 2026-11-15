<?php

declare(strict_types=1);

use App\Enums\AssemblyStatus;
use App\Enums\RevisionStatus;
use App\Enums\TransitionMode;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\Shot;
use Illuminate\Validation\ValidationException;

test('связи сериала сохраняют отдельные версии и исходный дубль', function (): void {
    $shot = Shot::factory()->create();
    $revision = $shot->scene()->firstOrFail()->episodeRevision()->firstOrFail();
    $series = $revision->episode()->firstOrFail()->series()->firstOrFail();
    $character = Character::factory()->for($series)->create();
    $characterRevision = CharacterRevision::factory()->for($character)->create();
    $revision->characterRevisions()->attach($characterRevision);
    $assembly = EpisodeAssembly::factory()->for($revision)->create();
    $prompt = Prompt::factory()->create(['shot_id' => $shot->id, 'take_number' => 1]);

    expect($shot->transition_mode)->toBe(TransitionMode::NEW_SCENE);
    expect($assembly->status)->toBe(AssemblyStatus::DRAFT);
    expect($revision->characterRevisions()->firstOrFail()->id)->toBe($characterRevision->id);
    expect($prompt->shot?->scene?->episodeRevision?->id)->toBe($revision->id);
    expect($assembly->episodeRevision?->episode?->series?->project)->not->toBeNull();
});

test('исторический prompt остается без связи с сериалом', function (): void {
    $prompt = Prompt::factory()->create();

    expect($prompt->shot_id)->toBeNull();
    expect($prompt->input_snapshot)->toBeNull();
    expect($prompt->shot)->toBeNull();
});

test('утвержденная версия персонажа не изменяется через модель', function (): void {
    $revision = CharacterRevision::factory()->approved()->create();

    expect(fn() => $revision->update(['description' => 'Changed']))
        ->toThrow(ValidationException::class);
    expect($revision->refresh()->status)->toBe(RevisionStatus::APPROVED);
    expect($revision->description)->not->toBe('Changed');
});
