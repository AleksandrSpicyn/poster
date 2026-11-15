<?php

declare(strict_types=1);

use App\Actions\SaveVideoAction;
use App\Enums\PromptStage;
use App\Events\VideoBuilt;
use App\Jobs\SaveVideoJob;
use App\Models\Prompt;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\EncodingException;

use function Pest\Laravel\assertDatabaseCount;

beforeEach(/** @throws Exception */ function (): void {
    Storage::fake('public');
    Event::fake([VideoBuilt::class]);
    GeneralSettings::fake(['duration' => 1]);
});

test('ролик не собирается без обоих источников', /** @throws Exception */ function (?string $video, ?string $audio): void {
    $prompt = Prompt::factory()->create(['video_url' => $video, 'audio_url' => $audio]);

    app(SaveVideoAction::class)->run($prompt);

    expect($prompt->refresh()->generated)
        ->toBeFalsy()
        ->and($prompt
            ->media()
            ->count())
        ->toBe(0);
    Event::assertNotDispatched(VideoBuilt::class);
})->with([[null, null], ['https://cdn.example.com/video.mp4', null], [null, 'https://cdn.example.com/audio.mp3']]);

test('FFmpeg собирает видео со звуком и сохраняет готовое медиа', /** @throws Exception */ function (): void {
    $disk = Storage::disk('public');

    Process::run(['ffmpeg', '-y', '-f', 'lavfi', '-i', 'color=c=black:s=64x64:d=1', '-c:v', 'libx264', $disk->path('input.mp4')])->throw();
    Process::run(['ffmpeg', '-y', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1', $disk->path('input.mp3')])->throw();

    $prompt = Prompt::factory()->create(['video_url' => $disk->path('input.mp4'), 'audio_url' => $disk->path('input.mp3')]);

    new SaveVideoJob($prompt)->handle(app(SaveVideoAction::class));

    expect($prompt->refresh()->generated)
        ->toBeTruthy()
        ->and($prompt
            ->media()
            ->count())
        ->toBe(1);

    $media = $prompt
        ->refresh()
        ->media()
        ->firstOrFail();

    expect(is_file($media->getPath()))->toBeTrue();

    $probe = Process::run(['ffprobe', '-v', 'quiet', '-show_streams', '-of', 'json', $media->getPath()])->throw();
    /** @var array{streams: list<array{codec_type: string}>} $streams */
    $streams = json_decode($probe->output(), true, flags: JSON_THROW_ON_ERROR);

    expect(array_column($streams['streams'], 'codec_type'))->toBe(['video', 'audio']);

    app(SaveVideoAction::class)->run($prompt);

    expect($prompt
        ->media()
        ->count())->toBe(1);
    Event::assertDispatchedTimes(VideoBuilt::class);
    Event::assertDispatched(VideoBuilt::class, fn(VideoBuilt $event) => $event
        ->getPrompt()
        ->is($prompt));
});

test('занятая блокировка не запускает вторую сборку', /** @throws Exception */ function (): void {
    $prompt = Prompt::factory()->create(['video_url' => 'video', 'audio_url' => 'audio']);
    $lock = Cache::lock('prompt-build:' . $prompt->id, 3720);

    expect($lock->get())->toBeTrue();

    try {
        app(SaveVideoAction::class)->run($prompt);
        expect($prompt->refresh()->generated)->toBeFalse();
        assertDatabaseCount('media', 0);
        Event::assertNotDispatched(VideoBuilt::class);
    } finally {
        $lock->release();
    }
});

test('сохраняет дорожку из того же файла без отдельного аудио и повторной сборки', function (): void {
    $disk = Storage::disk('public');
    Process::run([
        'ffmpeg', '-y', '-f', 'lavfi', '-i', 'color=c=black:s=64x64:d=1',
        '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
        '-c:v', 'libx264', '-c:a', 'aac', '-shortest', $disk->path('native.mp4'),
    ])->throw();
    $prompt = Prompt::factory()->create([
        'video_url' => $disk->path('native.mp4'),
        'audio_url' => $disk->path('native.mp4'),
    ]);
    expect($prompt->stage())->toBe(PromptStage::ASSEMBLY);

    app(SaveVideoAction::class)->run($prompt);

    $prompt->refresh();
    $media = $prompt->media()->firstOrFail();
    $probe = Process::run(['ffprobe', '-v', 'quiet', '-show_streams', '-of', 'json', $media->getPath()])->throw();
    /** @var array{streams: list<array{codec_type: string}>} $streams */
    $streams = json_decode($probe->output(), true, flags: JSON_THROW_ON_ERROR);

    expect(array_column($streams['streams'], 'codec_type'))->toBe(['video', 'audio']);
    expect($prompt->stage())->toBe(PromptStage::READY);
    expect($prompt->audio_url)->toBe($disk->path('native.mp4'));

    app(SaveVideoAction::class)->run($prompt);

    expect($prompt->media()->count())->toBe(1);
    Event::assertDispatchedTimes(VideoBuilt::class, 1);
});

test('файл без реальной аудиодорожки не становится готовым роликом', function (): void {
    $disk = Storage::disk('public');
    Process::run(['ffmpeg', '-y', '-f', 'lavfi', '-i', 'color=c=black:s=64x64:d=1', '-c:v', 'libx264', $disk->path('silent.mp4')])->throw();
    $prompt = Prompt::factory()->create([
        'video_url' => $disk->path('silent.mp4'),
        'audio_url' => $disk->path('silent.mp4'),
    ]);

    expect(fn() => app(SaveVideoAction::class)->run($prompt))->toThrow(EncodingException::class);

    expect($prompt->refresh()->generated)->toBeFalse();
    expect($prompt->media()->count())->toBe(0);
    Event::assertNotDispatched(VideoBuilt::class);
});
