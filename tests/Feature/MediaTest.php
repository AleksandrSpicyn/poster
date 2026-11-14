<?php

use App\Actions\SaveVideoAction;
use App\Events\VideoBuiled;
use App\Jobs\SaveVideoJob;
use App\Models\Prompt;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Event::fake([VideoBuiled::class]);
    GeneralSettings::fake(['duration' => 1]);
});

test('ролик не собирается без обоих источников', function (?string $video, ?string $audio): void {
    $prompt = Prompt::factory()->create(['video_url' => $video, 'audio_url' => $audio]);
    app(SaveVideoAction::class)->run($prompt);
    expect($prompt->fresh()->generated)->toBeFalsy()->and($prompt->media()->count())->toBe(0);
    Event::assertNotDispatched(VideoBuiled::class);
})->with([[null, null], ['https://cdn.example.com/video.mp4', null], [null, 'https://cdn.example.com/audio.mp3']]);

test('FFmpeg собирает видео со звуком и сохраняет готовое медиа', function (): void {
    $disk = Storage::disk('public');
    Process::run(['ffmpeg', '-y', '-f', 'lavfi', '-i', 'color=c=black:s=64x64:d=1', '-c:v', 'libx264', $disk->path('input.mp4')])->throw();
    Process::run(['ffmpeg', '-y', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1', $disk->path('input.mp3')])->throw();
    $prompt = Prompt::factory()->create(['video_url' => $disk->path('input.mp4'), 'audio_url' => $disk->path('input.mp3')]);

    (new SaveVideoJob($prompt))->handle(app(SaveVideoAction::class));

    expect($prompt->fresh()->generated)->toBeTruthy()->and($prompt->media()->count())->toBe(1);
    $media = $prompt->fresh()->getFirstMedia();
    expect(is_file($media->getPath()))->toBeTrue();
    $probe = Process::run(['ffprobe', '-v', 'quiet', '-show_streams', '-of', 'json', $media->getPath()])->throw();
    expect(collect(json_decode($probe->output(), true)['streams'])->pluck('codec_type')->all())->toBe(['video', 'audio']);
    Event::assertDispatched(VideoBuiled::class, fn(VideoBuiled $event) => $event->getPrompt()->is($prompt));
});
