<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\serialReferenceMedia;

test('утверждение фиксирует внешний вид и автора без генерации', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $media = serialReferenceMedia($context['revision']);
    $user = serialOperator();

    actingAs($user)
        ->post(route('characters.revisions.approve', [...$context['params'], 'revision' => $context['revision']]), ['lock_version' => 0])
        ->assertRedirect();

    expect($context['revision']->refresh()->status->value)->toBe('approved');
    expect($context['revision']->approved_by)->toBe($user->id);
    expect($context['character']->refresh()->current_revision_id)->toBe($context['revision']->id);
    expect(is_file($media->getPath()))->toBeTrue();
    Http::assertNothingSent();
});

test('версия без референса не утверждается', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->postJson(route('characters.revisions.approve', [...$context['params'], 'revision' => $context['revision']]), ['lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('references');

    expect($context['revision']->refresh()->status->value)->toBe('draft');
});
