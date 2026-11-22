<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

test('владелец сохраняет обложку и меняет кадрирование без замены файла', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $url = route('projects.cover.store', $project);

    actingAs($user)->post($url, ['file' => new UploadedFile(base_path('tests/Fixtures/portrait.png'), 'hero.png', 'image/png', null, true), 'x' => 35, 'y' => 65])
        ->assertSessionHasNoErrors()->assertRedirectToRoute('projects.cover.edit', $project);

    $image = $project->refresh()->getMedia(Project::COVER)->firstOrFail();
    expect($image->disk)->toBe('local');
    expect($image->getCustomProperty('focus_y'))->toBe(65);

    actingAs($user)->post($url, ['base_id' => $image->id, 'x' => 12, 'y' => 31])->assertSessionHasNoErrors();
    actingAs($user)->post($url, ['x' => 82, 'y' => 9])->assertSessionHasErrors('file');
    actingAs($user)->post($url, ['base_id' => $image->id, 'x' => 101, 'y' => 31])->assertSessionHasErrors('x');

    expect($image->refresh()->getCustomProperty('focus_x'))->toBe(12);
    expect($project->media()->count())->toBe(1);
});

test('чужой проект отклоняет загрузку без сохранения', function (): void {
    Storage::fake('local');
    $project = Project::factory()->create(['owner_id' => User::factory()->create()->id]);
    $user = User::factory()->create();

    actingAs($user)->post(route('projects.cover.store', $project), ['x' => 20, 'y' => 30])->assertNotFound();

    expect($project->media()->exists())->toBeFalse();
});
