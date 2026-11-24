<?php

declare(strict_types=1);

use App\Actions\Episodes\RequestEpisodeAssemblyAction;
use App\Actions\Upscale\RequestVideoUpscaleAction;
use App\Data\Episodes\AssemblyManifestData;
use App\Data\Episodes\AssemblyShotData;
use App\Data\Upscale\RequestVideoUpscaleData;
use App\Enums\AssemblyStatus;
use App\Enums\UpscaleExport;
use App\Enums\UpscaleOperationStatus;
use App\Jobs\AdvanceVideoUpscaleJob;
use App\Models\EpisodeAssembly;
use App\Models\VideoUpscaleOperation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Tests\Support\assemblyFixture;
use function Tests\Support\serialOperator;

require_once dirname(__DIR__, 3) . '/Support/Assemblies.php';

test('выпускает приватный экспорт выбранной сборки с исходным звуком', function (UpscaleExport $export): void {
    $disk = Storage::fake('local');
    Queue::fake();
    URL::forceScheme('https');
    ['revision' => $revision, 'prompt' => $prompt] = assemblyFixture();
    $assembly = app(RequestEpisodeAssemblyAction::class)->run($revision, new AssemblyManifestData([new AssemblyShotData($prompt->id, 0, 1000)]));
    $assembly->update(['status' => AssemblyStatus::READY]);

    $seriesId = $revision->episode()->firstOrFail()->series_id;
    config(['services.upscale.series_id' => $seriesId, 'services.upscale.budget_e8' => 100_000_000, 'services.fal.key' => 'test']);
    $result = $disk->path('fal.mp4');
    $width = $export->width();
    Process::run(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', "color=size={$width}x" . intdiv($width * 16, 9) . ':rate=24', '-t', '1', '-threads', '2', $result])->throw();
    Http::fake([
        'https://queue.fal.run/topaz/*' => Http::response(['request_id' => 'abc', 'status_url' => 'https://queue.fal.run/status', 'response_url' => 'https://queue.fal.run/result']),
        'https://queue.fal.run/status' => Http::response(['status' => 'COMPLETED']),
        'https://queue.fal.run/result' => Http::response(['video' => ['url' => 'https://v3.fal.media/export.mp4']]),
        'https://v3.fal.media/export.mp4' => Http::response(File::get($result), 200, ['Content-Type' => 'video/mp4']),
    ]);

    $action = app(RequestVideoUpscaleAction::class);
    $action->run($assembly, serialOperator(), new RequestVideoUpscaleData($export));
    $action->run($assembly, serialOperator(), new RequestVideoUpscaleData($export));

    $operation = VideoUpscaleOperation::query()->sole();
    $job = new AdvanceVideoUpscaleJob($operation->id);
    app()->call($job->handle(...));
    expect($operation->refresh()->status)->toBe(UpscaleOperationStatus::SUBMITTED);
    $input = $assembly->getFirstMedia(EpisodeAssembly::UPSCALE_INPUT);
    expect($input)->not->toBeNull();
    $streams = Process::run(['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_type,width,height', '-of', 'json', $input?->getPath()])->output();
    expect($streams)->toContain('"width": 486')->toContain('"height": 864');
    expect($streams)->not->toContain('"audio"');

    app()->call($job->handle(...));
    app()->call($job->handle(...));

    expect($operation->refresh()->status)->toBe(UpscaleOperationStatus::READY);
    $media = $assembly->fresh()?->getFirstMedia(EpisodeAssembly::UPSCALED);
    expect($media?->id)->toBe($operation->result_media_id);
    expect(hash_file('sha256', $media?->getPath() ?? ''))->toBe($operation->result_sha256);
    expect(Http::recorded(fn(Request $request): bool => $request->method() === 'POST'))->toHaveCount(1);
    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://v3.fal.media/export.mp4' && ! $request->hasHeader('Authorization'));
})->with(UpscaleExport::cases());
