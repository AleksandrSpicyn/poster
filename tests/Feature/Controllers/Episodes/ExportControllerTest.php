<?php

declare(strict_types=1);

use App\Enums\AssemblyStatus;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Support\WorkspaceOwner;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;

test('подписанный экспорт отдаёт только текущее принятое видео без сессии', function (): void {
    Storage::fake('local');
    $episode = Episode::factory()->create();
    $revision = EpisodeRevision::factory()->for($episode)->create();
    $assembly = EpisodeAssembly::factory()->for($revision)->create(['status' => AssemblyStatus::ACCEPTED]);
    $episode->update(['accepted_assembly_id' => $assembly->id]);
    $media = $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);
    $url = URL::temporarySignedRoute('episodes.export', now()->addHour(), ['assembly' => $assembly, 'media' => $media, 'owner' => WorkspaceOwner::id($assembly)]);

    get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('экспорт без подписи не раскрывает файл', function (): void {
    Storage::fake('local');
    $assembly = EpisodeAssembly::factory()->create();
    $media = $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);

    get(route('episodes.export', ['assembly' => $assembly, 'media' => $media, 'owner' => WorkspaceOwner::id($assembly)]))->assertForbidden();
});

test('экспорт отклоняет истёкшую подпись', function (): void {
    Storage::fake('local');
    $assembly = EpisodeAssembly::factory()->create();
    $media = $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);
    $url = URL::temporarySignedRoute('episodes.export', now()->subMinute(), ['assembly' => $assembly, 'media' => $media, 'owner' => WorkspaceOwner::id($assembly)]);

    get($url)->assertForbidden();
});

test('экспорт не открывает черновик даже по действующей подписи', function (): void {
    Storage::fake('local');
    $assembly = EpisodeAssembly::factory()->create(['status' => AssemblyStatus::READY]);
    $media = $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);
    $url = URL::temporarySignedRoute('episodes.export', now()->addHour(), ['assembly' => $assembly, 'media' => $media, 'owner' => WorkspaceOwner::id($assembly)]);

    get($url)->assertNotFound();
});

test('экспорт не отдаёт медиа другой сборки', function (): void {
    Storage::fake('local');
    $assembly = EpisodeAssembly::factory()->create(['status' => AssemblyStatus::ACCEPTED]);
    $other = EpisodeAssembly::factory()->create();
    $media = $other->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);
    $url = URL::temporarySignedRoute('episodes.export', now()->addHour(), ['assembly' => $assembly, 'media' => $media, 'owner' => WorkspaceOwner::id($assembly)]);

    get($url)->assertNotFound();
});
