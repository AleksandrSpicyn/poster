<?php

declare(strict_types=1);

use App\Models\Project;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('форма сериала возвращает паспорт проекта', function (): void {
    $context = serialCharacterContext();
    $params = ['project' => $context['project']->id, 'series' => $context['series']->id];

    actingAs(serialOperator())
        ->get(route('series.edit', $params))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Series/Form')
            ->where('project.id', $context['project']->id)
            ->where('series.id', $context['series']->id)
            ->where('audiences.children', 'Children')
            ->where('defaultAudioMode', 'native')
            ->where('audioModes', ['native' => 'Speech and sound', 'silent' => 'Silent video'])
            ->where('series.specification.audio_mode', 'native'));
});

test('гость не открывает форму сериала', function (): void {
    $context = serialCharacterContext();
    $params = ['project' => $context['project']->id, 'series' => $context['series']->id];

    get(route('series.edit', $params))->assertRedirect(route('login'));
});

test('форма скрывает сериал другого проекта', function (): void {
    $context = serialCharacterContext();
    $other = Project::factory()->create();

    actingAs(serialOperator())
        ->get(route('series.edit', ['project' => $other->id, 'series' => $context['series']->id]))
        ->assertNotFound();
});
