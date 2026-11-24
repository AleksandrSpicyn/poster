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
use App\Models\Project;
use App\Models\Prompt;
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

    config(['services.upscale.budget_e8' => 100_000_000, 'services.fal.key' => 'test']);
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

test('одиночный ролик на 301 секунду собирает два фрагмента в один выпуск с исходным звуком', function (): void {
    $disk = Storage::fake('local');
    Queue::fake();
    $owner = serialOperator();
    $project = Project::factory()->for($owner, 'owner')->create();
    $prompt = Prompt::factory()->for($project)->create(['generated' => true]);
    $sample = $disk->path('one.mp4');
    Process::run(['ffmpeg', '-v', 'error', '-f', 'lavfi', '-i', 'color=size=1080x1920:rate=24', '-t', '1', '-bf', '0', '-threads', '2', '-pix_fmt', 'yuv420p', $sample])->throw();
    $original = $disk->path('original.mp4');
    Process::run(['ffmpeg', '-v', 'error', '-stream_loop', '-1', '-i', $sample, '-f', 'lavfi', '-i', 'anullsrc',
        '-t', '301', '-c:v', 'copy', '-c:a', 'aac', $original])->throw();
    $prompt->addMedia($original)->toMediaCollection();

    $request = app(RequestVideoUpscaleAction::class);
    $export = new RequestVideoUpscaleData(UpscaleExport::FULL_HD);
    $operation = $request->run($prompt, $owner, $export);
    expect($request->run($prompt, $owner, $export)->id)->toBe($operation->id);
    $parts = $operation->parts()->get();
    expect($parts->pluck('source_snapshot.start_ms')->all())->toBe([0, 300000])
        ->and($parts->pluck('source_snapshot.duration_ms')->all())->toBe([300000, 1000]);
    $job = new AdvanceVideoUpscaleJob($operation->id);
    app()->call($job->handle(...));
    expect($operation->refresh()->status)->toBe(UpscaleOperationStatus::PROCESSING);

    foreach ($parts as $part) {
        $clip = $disk->path('part-' . $part->id . '.mp4');
        Process::run(['ffmpeg', '-v', 'error', '-stream_loop', '-1', '-i', $sample, '-t', (string) ($part->durationMs() / 1000), '-c', 'copy', $clip])->throw();
        $file = $prompt->addMedia($clip)->withCustomProperties(['upscale_operation_id' => $part->id])->toMediaCollection(EpisodeAssembly::UPSCALED, 'local');
        $part->forceFill(['status' => UpscaleOperationStatus::READY, 'result_media_id' => $file->id, 'result_sha256' => hash_file('sha256', $file->getPath())])->save();
    }

    app()->call($job->handle(...));

    expect($operation->refresh()->status)->toBe(UpscaleOperationStatus::READY);
    $file = $prompt->media()->findOrFail($operation->result_media_id);
    $streams = Process::run(['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_type,width,height,duration', '-of', 'json', $file->getPath()])->throw()->output();
    expect($streams)->toContain('"width": 1080', '"height": 1920', '"audio"', '301.000000');
    Http::assertNothingSent();
});
