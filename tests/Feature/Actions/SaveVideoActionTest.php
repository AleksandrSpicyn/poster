<?php

declare(strict_types=1);

use App\Actions\SaveVideoAction;
use App\Enums\PromptStage;
use App\Events\VideoBuilt;
use App\Jobs\SaveVideoJob;
use App\Models\Project;
use App\Models\Prompt;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\EncodingException;

use function Pest\Laravel\assertDatabaseCount;

test('выключенный проект не собирает ожидающий ролик', function (): void {
    $project = Project::factory()->create(['is_active' => false]);
    $prompt = Prompt::factory()->for($project)->create(['video_url' => 'https://cdn.example.com/video.mp4', 'audio_url' => 'https://cdn.example.com/audio.mp3']);
    Process::fake();

    app(SaveVideoAction::class)->run($prompt);

    expect($prompt->refresh()->generated)->toBeFalse();
    expect($prompt->media()->exists())->toBeFalse();
    expect($prompt->video_url)->toBe('https://cdn.example.com/video.mp4');
    Process::assertNothingRan();
    Event::assertNotDispatched(VideoBuilt::class);
});

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

    Event::assertDispatched(VideoBuilt::class, fn(VideoBuilt $event): bool => $event->getPrompt()->getFirstMediaUrl() !== '');

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

test('сборка использует длительность запуска после изменения общих настроек', function (): void {
    $disk = Storage::disk('public');
    Process::run([
        'ffmpeg', '-y', '-f', 'lavfi', '-i', 'color=c=black:s=64x64:d=2',
        '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2',
        '-c:v', 'libx264', '-c:a', 'aac', '-shortest', $disk->path('two-seconds.mp4'),
    ])->throw();
    $prompt = Prompt::factory()->create([
        'video_url' => $disk->path('two-seconds.mp4'), 'audio_url' => $disk->path('two-seconds.mp4'),
        'input_snapshot' => ['video_request' => ['duration' => 1]],
    ]);
    GeneralSettings::fake(['duration' => 2]);

    app(SaveVideoAction::class)->run($prompt);

    $media = $prompt->media()->firstOrFail();
    $probe = Process::run(['ffprobe', '-v', 'quiet', '-show_entries', 'format=duration', '-of', 'csv=p=0', $media->getPath()])->throw();
    expect((float) trim($probe->output()))->toBeGreaterThan(0.8)->toBeLessThan(1.3);
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
