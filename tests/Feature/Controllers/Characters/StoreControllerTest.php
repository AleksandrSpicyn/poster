<?php

declare(strict_types=1);

use App\Models\Character;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('создание персонажа сохраняет постоянную идентичность без генерации', function (): void {
    $context = serialCharacterContext();
    Process::fake();

    $response = actingAs(serialOperator())->post(route('characters.store', $context['params']), ['name' => 'Mira']);

    $character = Character::query()->where('name', 'Mira')->firstOrFail();
    $response->assertRedirect(route('characters.show', [...$context['params'], 'character' => $character]));
    expect($character->series_id)->toBe($context['series']->id);
    expect($character->current_revision_id)->toBeNull();
    Process::assertNothingRan();
});

test('персонаж без имени не создаётся', function (): void {
    $context = serialCharacterContext();

    actingAs(serialOperator())
        ->postJson(route('characters.store', $context['params']), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect($context['series']->characters()->count())->toBe(1);
});

test('гость не создаёт персонажа', function (): void {
    $context = serialCharacterContext();

    post(route('characters.store', $context['params']), ['name' => 'Mira'])->assertRedirect(route('login'));

    expect($context['series']->characters()->count())->toBe(1);
});
