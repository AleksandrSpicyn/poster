<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('форма сериала возвращает паспорт проекта', function (): void {
    $context = serialCharacterContext();
    $params = ['project' => $context['project']->id];

    actingAs(serialOperator())
        ->get(route('series.create', $params))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Series/Form')
            ->where('project.id', $context['project']->id)
            ->where('series', null)
            ->where('audiences.children', 'Children')
            ->where('defaultAudioMode', 'native')
            ->where('audioModes', ['native' => 'Speech and sound', 'silent' => 'Silent video']));
});

test('гость не открывает форму сериала', function (): void {
    $context = serialCharacterContext();
    $params = ['project' => $context['project']->id];

    get(route('series.create', $params))->assertRedirect(route('login'));
});
