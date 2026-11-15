<?php

declare(strict_types=1);

use App\Actions\MakeShortAction;
use App\Classes\Generators\VideoGenerator\VideoGenerator;
use App\Data\VideoCapabilitiesData;
use App\Data\VideoResultData;
use App\Enums\VideoAudioSource;
use App\Enums\VideoInputMode;
use App\Enums\VideoProvider;
use App\Events\AudioGenerating;
use App\Events\ImageGenerated;
use App\Events\PromptGenerated;
use App\Events\VideoGenerated;
use App\Events\VideoGenerating;
use App\Jobs\CheckVideoCreation;
use App\Models\Project;
use App\Settings\GeneralSettings;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\AnonymousAgent;

use function Pest\Laravel\mock;
use function Tests\Support\fakeCodex;
use function Tests\Support\fakeCodexImages;

test('сценарий выбирает источник звука по фактическому видеопровайдеру', function (bool $fallback): void {
    config([
        'services.video.provider' => 'seedance',
        'services.video.fallback_provider' => 'runway',
        'services.seedance.api_key' => 'test',
    ]);
    GeneralSettings::fake(['duration' => 10]);
    Storage::fake('public');
    fakeCodexImages();
    AnonymousAgent::fake(['A fox jumps'])->preventStrayPrompts();
    Event::fake([PromptGenerated::class, ImageGenerated::class, VideoGenerating::class, AudioGenerating::class]);
    Http::fake([
        '*contents/generations/tasks' => $fallback ? Http::response([], 429) : Http::response(['id' => 'seedance-1']),
        '*image_to_video' => Http::response(['id' => 'runway-1']),
        '*generate' => Http::response(['data' => ['taskId' => 'suno-1']]),
    ]);
    $project = Project::factory()->create();

    app(MakeShortAction::class)->run($project);

    $prompt = $project->prompts()->firstOrFail();
    expect($prompt->video_id)->toBe($fallback ? 'runway-1' : 'seedance-1');
    expect($prompt->video_provider)->toBe($fallback ? VideoProvider::RUNWAY : VideoProvider::SEEDANCE);
    expect($prompt->audio_id)->toBe($fallback ? 'suno-1' : null);
    expect($prompt->generated)->toBeFalse();
    Http::assertSent(function (Request $request): bool {
        $source = data_get($request->data(), 'content.1.image_url.url');

        return str_ends_with($request->url(), '/contents/generations/tasks')
            && is_string($source) && str_starts_with($source, 'data:image/png;base64,');
    });
    Http::assertSentCount($fallback ? 3 : 1);
    Event::assertDispatched(VideoGenerating::class, fn(VideoGenerating $event): bool => $event->getPrompt()->is($prompt));

    if ($fallback) {
        Event::assertDispatched(AudioGenerating::class);

        return;
    }

    Event::assertNotDispatched(AudioGenerating::class);
    Http::assertNotSent(fn(Request $request): bool => str_ends_with($request->url(), '/generate'));
})->with(['встроенный звук Seedance' => false, 'Suno после перехода на Runway' => true]);

test('конвейер использует возможности зарегистрированного драйвера независимо от его имени', function (): void {
    config([
        'services.video.provider' => 'hailuo',
        'services.video.drivers.hailuo' => VideoGenerator::class,
    ]);
    GeneralSettings::fake(['duration' => 10]);
    Storage::fake('public');
    fakeCodexImages();
    AnonymousAgent::fake(['A fox jumps'])->preventStrayPrompts();
    Event::fake([PromptGenerated::class, ImageGenerated::class, VideoGenerated::class, AudioGenerating::class]);
    Bus::fake();
    Http::fake();
    $driver = mock(VideoGenerator::class);
    $driver->shouldReceive('capabilities')->andReturn(new VideoCapabilitiesData(model: 'fake', modes: [VideoInputMode::FIRST_FRAME], minDuration: 1, maxDuration: 30, resolutions: ['720p'], defaultResolution: '720p', supportsAudioGeneration: true));
    $driver->shouldReceive('generate')->once()->andReturn('embedded-1');
    $driver->shouldReceive('audioSource')->andReturn(VideoAudioSource::EMBEDDED);
    $driver->shouldReceive('pollingAttempts')->once()->andReturn(17);
    $driver->shouldReceive('check')->with('embedded-1')->once()->andReturn(new VideoResultData('native.mp4', 'native.mp4'));
    $project = Project::factory()->create();

    app(MakeShortAction::class)->run($project);

    $prompt = $project->prompts()->firstOrFail();
    expect($prompt->video_provider)->toBe(VideoProvider::HAILUO);
    expect($prompt->audio_id)->toBeNull();
    Bus::assertDispatched(CheckVideoCreation::class, fn(CheckVideoCreation $job): bool => $job->tries === 17);
    Event::assertNotDispatched(AudioGenerating::class);

    app()->call([new CheckVideoCreation($prompt), 'handle']);

    expect($prompt->refresh()->video_url)->toBe('native.mp4');
    expect($prompt->audio_url)->toBe('native.mp4');
    Event::assertDispatched(VideoGenerated::class);
    Http::assertNothingSent();
});

test('отказ Codex останавливает конвейер до сохранения идеи и платных этапов', function (): void {
    $project = Project::factory()->create();
    $disk = Storage::fake('public');
    GeneralSettings::fake(['duration' => 10]);
    fakeCodex(fn(PendingProcess $process): ProcessResult => Process::result(errorOutput: 'Subscription limit', exitCode: 1));
    Event::fake([PromptGenerated::class, ImageGenerated::class, VideoGenerating::class, AudioGenerating::class]);

    expect(fn() => app(MakeShortAction::class)->run($project))->toThrow(RuntimeException::class, 'Codex generation failed');

    expect($project->prompts()->exists())->toBeFalse();
    Event::assertNotDispatched(PromptGenerated::class);
    Event::assertNotDispatched(ImageGenerated::class);
    Event::assertNotDispatched(VideoGenerating::class);
    Event::assertNotDispatched(AudioGenerating::class);
    $disk->assertEmpty();
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    Http::assertNothingSent();
});
