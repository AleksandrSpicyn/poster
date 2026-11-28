<?php

declare(strict_types=1);

use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('приватный просмотр сборки требует сессию и не кешируется публично', function (): void {
    Storage::fake('local');
    $assembly = EpisodeAssembly::factory()->recycle(workspaceOwner())->create();
    $media = $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);

    $response = actingAs(serialOperator())->get(route('episodes.media.preview', $media));

    $response->assertOk();

    expect($response->headers->hasCacheControlDirective('private'))->toBeTrue();
    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
});

test('гость не видит приватное медиа', function (): void {
    Storage::fake('local');
    $assembly = EpisodeAssembly::factory()->recycle(workspaceOwner())->create();
    $media = $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);

    get(route('episodes.media.preview', $media))->assertRedirect(route('login'));
});

test('просмотр не открывает медиа самостоятельного ролика через serial endpoint', function (): void {
    Storage::fake('local');
    $prompt = Prompt::factory()->recycle(workspaceOwner())->create();
    $media = $prompt->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(Prompt::SHOT_IMAGE);

    actingAs(serialOperator())->get(route('episodes.media.preview', $media))->assertNotFound();
});

test('подтверждённый посторонний пользователь не читает приватное видео', function (): void {
    Storage::fake('local');
    $assembly = EpisodeAssembly::factory()->recycle(workspaceOwner())->create();
    $media = $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);
    config(['services.admin.email' => 'operator@example.test', 'horizon.allowed_emails' => []]);

    actingAs(User::factory()->create(['email' => 'reader@example.test']))
        ->get(route('episodes.media.preview', $media))
        ->assertNotFound();

    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
});

test('выдаёт диапазон байтов архивного материала только владельцу', function (): void {
    Storage::fake('local');
    $user = serialOperator();
    $series = Series::factory()->recycle($user)->create(['archived_at' => now()]);
    $media = $series->addMedia(base_path('tests/Fixtures/shot.mp4'))
        ->preservingOriginal()
        ->withCustomProperties(['kind' => 'insert'])
        ->toMediaCollection(Series::ASSETS);
    $url = route('episodes.media.preview', $media);

    actingAs($user)
        ->get($url, ['Range' => 'bytes=37-68'])
        ->assertStatus(206)
        ->assertHeader('Content-Type', 'video/mp4')
        ->assertHeader('Content-Range', 'bytes 37-68/' . $media->size)
        ->assertHeader('Content-Length', '32');

    actingAs(User::factory()->create())
        ->get($url, ['Range' => 'bytes=37-68'])
        ->assertNotFound();
});
