<?php

declare(strict_types=1);

use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Tasks\Shots\DownloadShotVideo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('сохраняет готовый видеофрагмент больше десяти мегабайт приватно и без потери данных', function (): void {
    Storage::fake('local');
    Http::preventStrayRequests();
    $prompt = Prompt::factory()->create();
    $padding = 11 * 1024 * 1024;
    $video = File::get(base_path('tests/Fixtures/shot.mp4')) . pack('Na4', $padding + 8, 'free') . str_repeat("\0", $padding);
    Http::fake(['https://cdn.example.test/large-take.mp4' => Http::response($video)]);
    $operation = new GenerationOperation([
        'prompt_id' => $prompt->id,
        'result_snapshot' => ['video_url' => 'https://cdn.example.test/large-take.mp4'],
    ]);

    $media = app(DownloadShotVideo::class)->run($operation);

    expect($media->size)->toBe(strlen($video));
    expect($media->disk)->toBe('local');
    expect($media->collection_name)->toBe(Prompt::SHOT_VIDEO);
    expect(hash_file('sha256', $media->getPath()))->toBe(hash('sha256', $video));
    expect(Storage::disk('local')->allFiles('shot-work'))->toBe([]);
    Http::assertSentCount(1);
});
