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
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Tests\Support\assemblyFixture;

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
