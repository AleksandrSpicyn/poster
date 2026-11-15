<?php

declare(strict_types=1);

use App\Actions\MakeShortAction;
use App\Events\AudioGenerating;
use App\Events\ImageGenerated;
use App\Events\PromptGenerated;
use App\Events\VideoGenerating;
use App\Models\Project;
use App\Models\Prompt;
use App\Settings\GeneralSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;

use function Pest\Laravel\assertDatabaseCount;
use function Tests\Support\consoleCommand;
use function Tests\Support\fakeCodexImages;

beforeEach(/** @throws Exception */ function (): void {
    config([
        'services.video.provider' => 'hailuo',
        'services.video.fallback_provider' => 'runway',
        'services.hailuo.api_key' => 'test', 'services.hailuo.group_id' => 'group',
        'services.runway.api_key' => 'test', 'services.suno_unofficial.api_key' => 'test',
    ]);

    GeneralSettings::fake(['duration' => 10]);
});

test('сценарий генерации сохраняет результаты и запускает асинхронные этапы', function (bool $fallback, string $expectedId): void {
    $disk = Storage::fake('public');
    fakeCodexImages();
    Event::fake([PromptGenerated::class, ImageGenerated::class, VideoGenerating::class, AudioGenerating::class]);
    $openai = OpenAI::fake([
        ChatResponse::fake(['choices' => [['message' => ['content' => 'A fox in Tokyo']]]]),
    ]);
    Http::fake([
        '*video_generation' => $fallback ? Http::response([], 503) : Http::response(['task_id' => 'video-1']),
        '*image_to_video' => Http::response(['id' => 'video-fallback']),
        '*generate' => Http::response(['data' => ['taskId' => 'audio-1']]),
    ]);

    $project = Project::factory()->create();

    Prompt::factory()
        ->for($project)
        ->create(['prompt' => 'Earlier idea']);

    consoleCommand('app:make-short')->assertSuccessful();

    $prompt = $project
        ->prompts()
        ->latest('id')
        ->firstOrFail();

    expect($prompt->prompt)
        ->toBe('A fox in Tokyo')
        ->and($prompt->image_url)
        ->toBe($disk->url($disk->allFiles('generated-images')[0]))
        ->and($prompt->video_id)
        ->toBe($expectedId)
        ->and($prompt->audio_id)
        ->toBe('audio-1')
        ->and($prompt->generated)
        ->toBeFalsy();
    Event::assertDispatched(PromptGenerated::class);
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    $disk->assertCount('generated-images', 1);
    Event::assertDispatched(ImageGenerated::class);
    Event::assertDispatched(VideoGenerating::class, fn(VideoGenerating $event): bool => $event->getAlternative() === $fallback);
    Event::assertDispatched(AudioGenerating::class);
    $openai
        ->chat()
        ->assertSent(fn(string $method, array $parameters): bool => is_string(data_get($parameters, 'messages.0.content')) && str_contains(data_get($parameters, 'messages.0.content'), 'Earlier idea'));
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/generate')
        && $request['instrumental'] === true && $request['callBackUrl'] === route('webhook.suno'));
})->with(['основной провайдер' => [false, 'video-1'], 'резервный провайдер' => [true, 'video-fallback']]);

test('отказ обоих видео-провайдеров не запускает аудио и не отмечает идею готовой', function (): void {
    $disk = Storage::fake('public');
    fakeCodexImages();
    Event::fake([VideoGenerating::class, AudioGenerating::class]);
    OpenAI::fake([
        ChatResponse::fake(['choices' => [['message' => ['content' => 'An idea']]]]),
    ]);
    Http::fake(['*video_generation' => Http::response([], 503), '*image_to_video' => Http::response([], 503)]);

    $project = Project::factory()->create();

    expect(fn() => app(MakeShortAction::class)->run($project))->toThrow(RuntimeException::class);

    $prompt = $project
        ->prompts()
        ->firstOrFail();

    expect($prompt->image_url)
        ->toBe($disk->url($disk->allFiles('generated-images')[0]))
        ->and($prompt->video_id)
        ->toBeNull()
        ->and($prompt->audio_id)
        ->toBeNull()
        ->and($prompt->generated)
        ->toBeFalse();
    Event::assertNotDispatched(VideoGenerating::class);
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    $disk->assertCount('generated-images', 1);
    Event::assertNotDispatched(AudioGenerating::class);
    Http::assertSentCount(2);
});

test('команда генерации без проектов не выполняет внешних запросов', function (): void {
    $openai = OpenAI::fake();
    Http::fake();
    Process::fake();

    consoleCommand('app:make-short')->assertSuccessful();
    Http::assertNothingSent();
    Process::assertNothingRan();
    $openai->assertNothingSent();

    assertDatabaseCount('prompts', 0);
});
