<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('старая форма сериала ведёт к настройкам проекта', function (): void {
    $context = serialCharacterContext();
    $params = ['project' => $context['project']->id];

    actingAs(serialOperator())
        ->get(route('series.create', $params))
        ->assertRedirect(route('projects.edit', $context['project']));
});

test('гость не открывает форму сериала', function (): void {
    $context = serialCharacterContext();
    $params = ['project' => $context['project']->id];

    get(route('series.create', $params))->assertRedirect(route('login'));
});
