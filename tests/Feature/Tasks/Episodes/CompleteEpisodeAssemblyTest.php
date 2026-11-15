<?php

declare(strict_types=1);

use App\Data\Episodes\BuiltEpisodeVideoData;
use App\Data\Episodes\ContinuationMediaData;
use App\Enums\AssemblyStatus;
use App\Models\EpisodeAssembly;
use App\Tasks\Episodes\CompleteEpisodeAssembly;
use App\Tasks\Episodes\FailEpisodeAssembly;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

test('устаревший worker не завершает и не ломает новую попытку сборки', function (): void {
    $disk = Storage::fake('local');
    $stale = EpisodeAssembly::factory()->create(['status' => AssemblyStatus::BUILDING, 'lock_version' => 1]);
    $current = EpisodeAssembly::query()->findOrFail($stale->id);
    $current->update(['lock_version' => 2]);

    $workspace = $disk->path('episode-builds/old-worker');
    File::ensureDirectoryExists($workspace);
    $video = new BuiltEpisodeVideoData($workspace . '/missing.mp4', 1000, $workspace);
    $continuation = new ContinuationMediaData($workspace . '/frame.png', $workspace . '/tail.mp4', 958);

    expect(fn() => app(CompleteEpisodeAssembly::class)->run($stale, $video, $continuation))
        ->toThrow(RuntimeException::class, 'The assembly is no longer being built.');
    app(FailEpisodeAssembly::class)->run($stale, $workspace);

    expect($current->refresh()->status)->toBe(AssemblyStatus::BUILDING);
    expect($current->lock_version)->toBe(2);
    expect($current->media()->count())->toBe(0);

    $disk->assertMissing('episode-builds/old-worker');
});
