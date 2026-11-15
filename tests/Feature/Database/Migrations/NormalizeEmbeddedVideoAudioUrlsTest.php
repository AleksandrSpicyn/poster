<?php

declare(strict_types=1);

use App\Enums\VideoProvider;
use App\Models\Prompt;
use Illuminate\Database\Migrations\Migration;

test('старые встроенные дорожки нормализуются без изменения готовых источников', function (): void {
    $old = Prompt::factory()->create(['video_provider' => VideoProvider::SEEDANCE, 'video_url' => 'native.mp4']);
    $separate = Prompt::factory()->create(['video_provider' => VideoProvider::RUNWAY, 'video_url' => 'silent.mp4']);
    $complete = Prompt::factory()->create(['video_provider' => VideoProvider::SEEDANCE, 'video_url' => 'video.mp4', 'audio_url' => 'audio.mp3']);
    $pending = Prompt::factory()->create(['video_provider' => VideoProvider::SEEDANCE, 'video_id' => 'task-1']);
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_11_15_143428_normalize_embedded_video_audio_urls.php');
    assert(method_exists($migration, 'up'));

    $migration->up();
    $migration->up();

    expect($old->refresh()->audio_url)->toBe('native.mp4');
    expect($separate->refresh()->audio_url)->toBeNull();
    expect($complete->refresh()->audio_url)->toBe('audio.mp3');
    expect($pending->refresh()->audio_url)->toBeNull();
});
