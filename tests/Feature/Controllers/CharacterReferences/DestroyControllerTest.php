<?php

declare(strict_types=1);

use App\Models\CharacterRevision;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\serialReferenceMedia;

test('удаление референса черновика очищает файл и обновляет версию', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $media = serialReferenceMedia($context['revision']);
    $path = $media->getPath();

    actingAs(serialOperator())
        ->delete(route('characters.references.destroy', [...$context['params'], 'revision' => $context['revision'], 'media' => $media]), ['lock_version' => 0])
        ->assertRedirect();

    expect(is_file($path))->toBeFalse();
    expect($context['revision']->media()->count())->toBe(0);
    expect($context['revision']->refresh()->lock_version)->toBe(1);
});

test('удаление не меняет чужую версию и её файл', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $other = CharacterRevision::factory()->create();
    $media = serialReferenceMedia($other);

    actingAs(serialOperator())
        ->deleteJson(route('characters.references.destroy', [...$context['params'], 'revision' => $context['revision'], 'media' => $media]), ['lock_version' => 0])
        ->assertNotFound();

    expect(is_file($media->getPath()))->toBeTrue();
    expect($other->media()->count())->toBe(1);
});
