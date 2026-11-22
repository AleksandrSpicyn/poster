<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\serialReferenceMedia;

test('карточка возвращает версии и безопасные ссылки на приватные референсы', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $media = serialReferenceMedia($context['revision']);
    $episode = Episode::factory()->for($context['series'])->create();
    $scripts = EpisodeRevision::factory()->count(2)->for($episode)->create();

    foreach ($scripts as $script) {
        $script->characterRevisions()->attach($context['revision']);
    }

    actingAs(serialOperator())
        ->get(route('characters.show', $context['params']))
        ->assertOk()
        ->assertDontSee($media->getPath())
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Characters/Show')
            ->where('episodesCount', 1)
            ->where('character.id', $context['character']->id)
            ->where('revisions.data.0.references.0.id', $media->id)
            ->where('revisions.data.0.references.0.url', route('characters.references.show', ['revision' => $context['revision'], 'media' => $media])));
});

test('портрет утверждённой версии сохраняется на другой странице истории', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $character = $context['character'];
    $number = $context['revision']->number + 1;
    $approved = CharacterRevision::factory()
        ->for($character)
        ->approved()
        ->create(['number' => $number]);
    $portrait = serialReferenceMedia($approved);
    $character->update(['current_revision_id' => $approved->id]);

    foreach (range($number + 1, $number + 5) as $newerNumber) {
        CharacterRevision::factory()
            ->for($character)
            ->create(['number' => $newerNumber]);
    }

    $response = actingAs(serialOperator())
        ->get(route('characters.show', $context['params']));

    $response
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->has('revisions.data', 5)
            ->where('revisions.data.4.number', $number + 1)
            ->where('character.current_revision.id', $approved->id)
            ->where('character.current_revision.references.0.url', route('characters.references.show', ['revision' => $approved, 'media' => $portrait])));
});

test('карточка не раскрывает персонажа чужого сериала', function (): void {
    $context = serialCharacterContext();
    $other = Character::factory()->create();

    actingAs(serialOperator())
        ->get(route('characters.show', [...$context['params'], 'character' => $other]))
        ->assertNotFound();
});

test('гость не видит персонажа', function (): void {
    $context = serialCharacterContext();

    get(route('characters.show', $context['params']))->assertRedirect(route('login'));
});
