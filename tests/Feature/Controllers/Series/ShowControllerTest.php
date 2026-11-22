<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\Episode;
use App\Models\Project;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('карточка показывает только персонажей и эпизоды сериала', function (): void {
    $context = serialCharacterContext();
    Character::factory()->create(['name' => 'Foreign actor']);
    $episode = Episode::factory()->recycle(workspaceOwner())->for($context['series'])->create();
    Episode::factory()->recycle(workspaceOwner())->create(['title' => 'Foreign episode']);

    actingAs(serialOperator())
        ->get(route('series.show', $context['params']))
        ->assertOk()
        ->assertDontSee('Foreign actor')
        ->assertDontSee('Foreign episode')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Series/Show')
            ->where('series.id', $context['series']->id)
            ->where('characters.data.0.id', $context['character']->id)
            ->has('characters.data', 1)
            ->where('episodes.0.id', $episode->id));
});

test('сериал скрыт в чужом проекте', function (): void {
    $context = serialCharacterContext();
    $other = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(serialOperator())
        ->get(route('series.show', ['project' => $other, 'series' => $context['series']]))
        ->assertNotFound();
});

test('гость не видит карточку сериала', function (): void {
    $context = serialCharacterContext();

    get(route('series.show', $context['params']))->assertRedirect(route('login'));
});
