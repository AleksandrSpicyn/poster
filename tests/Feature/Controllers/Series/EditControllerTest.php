<?php

declare(strict_types=1);

use App\Models\Project;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;

test('старая форма сериала ведёт к настройкам проекта', function (): void {
    $context = serialCharacterContext();
    $params = ['project' => $context['project']->id, 'series' => $context['series']->id];

    actingAs(serialOperator())
        ->get(route('series.edit', $params))
        ->assertRedirect(route('projects.edit', $context['project']));
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
