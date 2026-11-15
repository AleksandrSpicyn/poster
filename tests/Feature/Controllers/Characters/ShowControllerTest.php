<?php

declare(strict_types=1);

use App\Models\Character;
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

    actingAs(serialOperator())
        ->get(route('characters.show', $context['params']))
        ->assertOk()
        ->assertDontSee($media->getPath())
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Characters/Show')
            ->where('character.id', $context['character']->id)
            ->where('revisions.data.0.references.0.id', $media->id)
            ->where('revisions.data.0.references.0.url', route('characters.references.show', ['revision' => $context['revision'], 'media' => $media])));
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
