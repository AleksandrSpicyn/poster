<?php

declare(strict_types=1);

use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialOperator;

test('приватный просмотр сборки требует сессию и не кешируется публично', function (): void {
    Storage::fake('local');
    $assembly = EpisodeAssembly::factory()->create();
    $media = $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);

    $response = actingAs(serialOperator())->get(route('episodes.media.preview', $media));

    $response->assertOk();

    expect($response->headers->hasCacheControlDirective('private'))->toBeTrue();
    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
});

test('гость не видит приватное медиа', function (): void {
    Storage::fake('local');
    $assembly = EpisodeAssembly::factory()->create();
    $media = $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);

    get(route('episodes.media.preview', $media))->assertRedirect(route('login'));
});

test('просмотр не открывает медиа самостоятельного ролика через serial endpoint', function (): void {
    Storage::fake('local');
    $prompt = Prompt::factory()->create();
    $media = $prompt->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(Prompt::SHOT_IMAGE);

    actingAs(serialOperator())->get(route('episodes.media.preview', $media))->assertNotFound();
});

test('подтверждённый посторонний пользователь не читает приватное видео', function (): void {
    Storage::fake('local');
    $assembly = EpisodeAssembly::factory()->create();
    $media = $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);
    config(['services.admin.email' => 'operator@example.test', 'horizon.allowed_emails' => []]);

    actingAs(User::factory()->create(['email' => 'reader@example.test']))
        ->get(route('episodes.media.preview', $media))
        ->assertForbidden();

    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
});
