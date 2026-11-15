<?php

declare(strict_types=1);

use App\Models\EpisodeRevision;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;

test('утверждение фиксирует текущий сценарий', function (): void {
    $context = episodeHttpFixture(false);

    actingAs($context['user'])->post(route('episodes.scripts.approve', $context['params']))->assertRedirect();

    expect($context['revision']->refresh()->status->value)->toBe('approved');
    expect($context['revision']->approved_by)->toBe($context['user']->id);
});

test('нельзя утвердить чужую версию под текущим эпизодом', function (): void {
    $context = episodeHttpFixture(false);
    $other = EpisodeRevision::factory()->create();

    actingAs($context['user'])
        ->postJson(route('episodes.scripts.approve', [...$context['params'], 'revision' => $other]))
        ->assertNotFound();

    expect($other->refresh()->status->value)->toBe('draft');
});
