<?php

declare(strict_types=1);

use App\Actions\Episodes\BuildEpisodeAssemblyAction;
use App\Actions\Episodes\RequestEpisodeAssemblyAction;
use App\Data\Episodes\AssemblyManifestData;
use App\Data\Episodes\AssemblyShotData;
use App\Enums\AssemblyStatus;
use App\Enums\SeriesAudioMode;
use App\Jobs\BuildEpisodeAssemblyJob;
use App\Models\EpisodeAssembly;
use App\Models\Series;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Tests\Support\assemblyFixture;
use function Tests\Support\serialVoiceWav;

require_once dirname(__DIR__, 3) . '/Support/Assemblies.php';

test('выключение проекта блокирует монтаж уже стоящий в очереди', function (): void {
    Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision, 'prompt' => $prompt] = assemblyFixture();
    $assembly = app(RequestEpisodeAssemblyAction::class)->run($revision, new AssemblyManifestData([new AssemblyShotData($prompt->id, 0, 1000)]));
    $prompt->project?->update(['is_active' => false]);
    Process::fake();

    expect(fn() => app(BuildEpisodeAssemblyAction::class)->run($assembly))->toThrow(ValidationException::class);

    expect($assembly->refresh()->status)->toBe(AssemblyStatus::FAILED);
    expect($assembly->getFirstMedia(EpisodeAssembly::VIDEO))->toBeNull();
    Process::assertNothingRan();
});

test('реальный FFmpeg собирает видео и финальные референсы один раз', function (): void {
    $disk = Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision, 'prompt' => $prompt] = assemblyFixture();
    $assembly = app(RequestEpisodeAssemblyAction::class)->run($revision, new AssemblyManifestData([new AssemblyShotData($prompt->id, 0, 1000)]));
    $action = app(BuildEpisodeAssemblyAction::class);

    $action->run($assembly);
    $action->run($assembly);

    $assembly->refresh();
    expect($assembly->status)->toBe(AssemblyStatus::READY);
    expect($assembly->duration_ms)->toBeGreaterThanOrEqual(950)->toBeLessThanOrEqual(1100);
    expect($assembly->media)->toHaveCount(3);
    $media = $assembly->media;
    $video = $media->where('collection_name', EpisodeAssembly::VIDEO)->sole();
    $frame = $media->where('collection_name', EpisodeAssembly::CONTINUATION_FRAME)->sole();
    $tail = $media->where('collection_name', EpisodeAssembly::CONTINUATION_TAIL)->sole();

    foreach ([$video, $frame, $tail] as $file) {
        $disk->assertExists($file->getPathRelativeToRoot());
    }

    expect(getimagesize($frame->getPath()))->toMatchArray([0 => 720, 1 => 1280]);
    expect($disk->allDirectories('episode-builds'))->toBe([]);

    $probe = Process::run(['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_type,width,height', '-of', 'json', $video->getPath()]);
    expect($probe->successful())->toBeTrue();
    expect($probe->output())->toContain('"audio"')->toContain('"width": 720')->toContain('"height": 1280');
});

test('ошибка FFmpeg не создает готовое медиа', function (): void {
    $disk = Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision, 'prompt' => $prompt] = assemblyFixture();
    $assembly = app(RequestEpisodeAssemblyAction::class)->run($revision, new AssemblyManifestData([new AssemblyShotData($prompt->id, 0, 1000)]));
    Process::preventStrayProcesses();
    Process::fake(['*' => Process::result(exitCode: 1)]);

    expect(fn() => app(BuildEpisodeAssemblyAction::class)->run($assembly))->toThrow(RuntimeException::class);

    expect($assembly->refresh()->status)->toBe(AssemblyStatus::FAILED);
    expect($assembly->getFirstMedia(EpisodeAssembly::VIDEO))->toBeNull();
    expect($assembly->error_message)->not->toContain('/var/www');
    expect($disk->allDirectories('episode-builds'))->toBe([]);
});

test('немой монтаж убирает фактический звук дубля по сохранённому режиму', function (): void {
    $disk = Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision, 'prompt' => $prompt, 'media' => $media] = assemblyFixture();
    $input = $disk->path('with-sound.mp4');
    Process::run([
        'ffmpeg', '-v', 'error', '-nostdin', '-y', '-i', $media->getPath(),
        '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
        '-t', '1', '-c:v', 'copy', '-c:a', 'aac', $input,
    ])->throw();
    $media->delete();
    $prompt->addMedia($input)->toMediaCollection('shot-video', 'local');
    $prompt->update(['input_snapshot' => [...($prompt->input_snapshot ?? []), 'audio_mode' => SeriesAudioMode::SILENT->value]]);
    $assembly = app(RequestEpisodeAssemblyAction::class)->run($revision, new AssemblyManifestData([new AssemblyShotData($prompt->id, 0, 1000)]));

    app(BuildEpisodeAssemblyAction::class)->run($assembly);

    expect(data_get($assembly->manifest, 'shots.0.mute_audio'))->toBeTrue();
    expect($assembly->refresh()->status)->toBe(AssemblyStatus::READY);

    $output = $assembly->media()->where('collection_name', EpisodeAssembly::VIDEO)->firstOrFail();
    $levels = Process::run(['ffmpeg', '-hide_banner', '-nostdin', '-i', $output->getPath(), '-af', 'volumedetect', '-vn', '-f', 'null', '-']);
    expect($levels->successful())->toBeTrue();
    expect($levels->errorOutput())->toContain('max_volume: -91.0 dB');

    $disk->assertExists($output->getPathRelativeToRoot());
});

test('собирает фрагмент короче ста миллисекунд', function (int $start, int $end): void {
    Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision, 'prompt' => $prompt] = assemblyFixture();
    $assembly = app(RequestEpisodeAssemblyAction::class)->run($revision, new AssemblyManifestData([new AssemblyShotData($prompt->id, $start, $end)]));

    app(BuildEpisodeAssemblyAction::class)->run($assembly);

    expect($assembly->refresh()->status)->toBe(AssemblyStatus::READY);
    expect($assembly->manifest['duration_ms'])->toBe($end - $start);
    expect($assembly->duration_ms)->toBe($end - $start);

    $video = $assembly->getFirstMedia(EpisodeAssembly::VIDEO);
    assert($video !== null);
    $decoded = Process::run(['ffmpeg', '-v', 'error', '-nostdin', '-i', $video->getPath(), '-map', '0:v:0', '-frames:v', '1', '-f', 'framemd5', '-']);

    expect($decoded->successful())->toBeTrue();
    expect($decoded->output())->toMatch('/^0,.*[a-f0-9]{32}$/m');
})->with([[999, 1000], [310, 330], [480, 579]]);

test('монтирует титр, вставку и записанный звук с учётом обрезки', function (): void {
    Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision, 'prompt' => $prompt] = assemblyFixture();
    $shot = $prompt->shot()->firstOrFail();
    $series = $revision->episode()->firstOrFail()->series()->firstOrFail();
    $assets = [];
    $cues = [];
    $sources = ['insert' => File::get(base_path('tests/Fixtures/portrait.png')), 'sound' => serialVoiceWav()];

    foreach ($sources as $kind => $bytes) {
        $digest = hash('sha256', $bytes);
        $asset = $series->addMediaFromString($bytes)->usingFileName($kind === 'insert' ? 'cover.png' : 'sound.wav')
            ->withCustomProperties(['kind' => $kind, 'checksum' => $digest])->toMediaCollection(Series::ASSETS);
        $assets[$asset->id] = ['kind' => $kind, 'checksum' => $digest];
        $cues[] = ['kind' => $kind, 'asset_id' => $asset->id, 'text' => $kind, 'start_ms' => 0, 'end_ms' => 650];
    }

    $cues[] = ['kind' => 'text', 'text' => 'Маму: 100%', 'start_ms' => 600, 'end_ms' => 1000];
    $direction = ['cues' => $cues];
    $shot->update(['camera' => [...$shot->camera, 'direction' => $direction]]);
    $prompt->update(['input_snapshot' => [...($prompt->input_snapshot ?? []), 'direction' => $direction, 'series_assets' => $assets]]);

    $assembly = app(RequestEpisodeAssemblyAction::class)->run($revision, new AssemblyManifestData([new AssemblyShotData($prompt->id, 200, 1000)]));
    app(BuildEpisodeAssemblyAction::class)->run($assembly);

    expect($assembly->refresh()->status)->toBe(AssemblyStatus::READY)
        ->and($assembly->manifest['requires_audio_review'])->toBeTrue()
        ->and($assembly->duration_ms)->toBeGreaterThanOrEqual(770)->toBeLessThanOrEqual(870);
    $path = $assembly->getFirstMedia(EpisodeAssembly::VIDEO)?->getPath();
    $streams = Process::run(['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_type', '-of', 'csv=p=0', $path]);
    expect($streams->output())->toContain('audio')->toContain('video');
});
