<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

test('редактор открывает обложку своего проекта', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $url = route('projects.cover.edit', $project);

    $response = actingAs($owner)->get($url);

    $response
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Projects/Cover')
            ->where('project.id', $project->id)
            ->where('project.cover', null));

    actingAs(User::factory()->create())
        ->get($url)
        ->assertNotFound();
});
