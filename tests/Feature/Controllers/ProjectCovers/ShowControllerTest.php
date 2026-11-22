<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

test('кадр проекта доступен только владельцу', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $prompt = Prompt::factory()->for($project)->create();
    $prompt->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(Prompt::SHOT_IMAGE);
    $url = route('projects.cover.show', $project);

    actingAs($user)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    actingAs(User::factory()->create())->get($url)->assertNotFound();
});
