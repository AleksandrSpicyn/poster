<?php

declare(strict_types=1);

use App\Actions\MakeShortAction;
use App\Classes\Generators\AudioGenerator\SunoUnofficial\SunoUnofficial;
use App\Classes\Generators\VideoGenerator\Hailuo\Hailuo;
use App\Classes\Generators\VideoGenerator\Runway\Runway;
use App\Events\AudioGenerating;
use App\Events\ImageGenerated;
use App\Events\PromptGenerated;
use App\Events\VideoGenerating;
use App\Exceptions\VideoGeneratingException;
use App\Models\Project;
use App\Models\Prompt;
use App\Settings\GeneralSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;
use OpenAI\Responses\Images\CreateResponse as ImageResponse;

beforeEach(/** @throws Exception */ function (): void {
    config([
        'services.hailuo.api_key' => 'test', 'services.hailuo.group_id' => 'group',
        'services.runway.api_key' => 'test', 'services.suno_unofficial.api_key' => 'test',
    ]);
    GeneralSettings::fake(['duration' => 10]);
});

test('сценарий генерации сохраняет результаты и запускает асинхронные этапы', function (bool $fallback): void {
    Event::fake([PromptGenerated::class, ImageGenerated::class, VideoGenerating::class, AudioGenerating::class]);
    $openai = OpenAI::fake([
        ChatResponse::fake(['choices' => [['message' => ['content' => 'A fox in Tokyo']]]]),
        ImageResponse::fake(['data' => [['url' => 'https://cdn.example.com/image.png']]]),
    ]);
    Http::fake([
        '*video_generation' => $fallback ? Http::response([], 503) : Http::response(['task_id' => 'video-1']),
        '*image_to_video' => Http::response(['id' => 'video-fallback']),
        '*generate' => Http::response(['data' => ['taskId' => 'audio-1']]),
    ]);
    $project = Project::factory()->create();
    Prompt::factory()->for($project)->create(['prompt' => 'Earlier idea']);

    consoleCommand('app:make-short')->assertSuccessful();

    $prompt = $project->prompts()->latest('id')->firstOrFail();
    expect($prompt->prompt)->toBe('A fox in Tokyo')
        ->and($prompt->image_url)->toBe('https://cdn.example.com/image.png')
        ->and($prompt->video_id)->toBe($fallback ? 'video-fallback' : 'video-1')
        ->and($prompt->audio_id)->toBe('audio-1')->and($prompt->generated)->toBeFalsy();
    Event::assertDispatched(PromptGenerated::class);
    Event::assertDispatched(ImageGenerated::class);
    Event::assertDispatched(VideoGenerating::class, fn(VideoGenerating $event): bool => $event->getAlternative() === $fallback);
    Event::assertDispatched(AudioGenerating::class);
    $openai->chat()->assertSent(fn(string $method, array $parameters): bool => is_string(data_get($parameters, 'messages.0.content')) && str_contains(data_get($parameters, 'messages.0.content'), 'Earlier idea'));
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/generate')
        && $request['instrumental'] === true && $request['callBackUrl'] === route('webhook.suno'));
})->with([false, true]);

test('Hailuo возвращает только готовое видео', /** @throws Exception */ function (array $response, ?string $expected): void {
    Http::fake([
        '*query/video_generation/*' => Http::response($response),
        '*files/retrieve*' => Http::response(['file' => ['download_url' => 'https://cdn.example.com/video.mp4']]),
    ]);
    expect(app(Hailuo::class)->check('video-1'))->toBe($expected);
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), 'task_id=video-1'));
})->with([[[], null], [['file_id' => 'file-1'], 'https://cdn.example.com/video.mp4']]);

test('Runway различает ожидание успех и отказ', /** @throws Exception */ function (string $status): void {
    Http::fake(['*tasks/video-1' => Http::response([
        'id' => 'video-1', 'status' => $status, 'output' => ['https://cdn.example.com/video.mp4'], 'failure' => 'Failed',
    ])]);

    if ($status === 'FAILED') {
        expect(/** @throws Exception */ fn() => app(Runway::class)->check('video-1'))->toThrow(VideoGeneratingException::class);
    } else {
        expect(app(Runway::class)->check('video-1'))->toBe($status === 'SUCCEEDED' ? 'https://cdn.example.com/video.mp4' : null);
    }

    Http::assertSentCount(1);
})->with(['PENDING', 'SUCCEEDED', 'FAILED']);

test('ошибка генератора не маскируется успешным результатом', /** @throws Exception */ function (string $generator): void {
    Http::fake(['*' => Http::response(['message' => 'Unavailable'], 503)]);
    /** @var SunoUnofficial|Runway|Hailuo $client */
    $client = app($generator);
    expect(fn() => $client->generate('image', 'idea'))->toThrow(RuntimeException::class);
    Http::assertSentCount(1);
})->with([Hailuo::class, Runway::class, SunoUnofficial::class]);

test('отказ обоих видео-провайдеров не запускает аудио и не отмечает идею готовой', function (): void {
    Event::fake([VideoGenerating::class, AudioGenerating::class]);
    OpenAI::fake([
        ChatResponse::fake(['choices' => [['message' => ['content' => 'An idea']]]]),
        ImageResponse::fake(['data' => [['url' => 'https://cdn.example.com/image.png']]]),
    ]);
    Http::fake(['*video_generation' => Http::response([], 503), '*image_to_video' => Http::response([], 503)]);
    $project = Project::factory()->create();

    expect(fn() => app(MakeShortAction::class)->run($project))->toThrow(RuntimeException::class);

    $prompt = $project->prompts()->firstOrFail();
    expect($prompt->image_url)->toBe('https://cdn.example.com/image.png')
        ->and($prompt->video_id)->toBeNull()->and($prompt->audio_id)->toBeNull()->and($prompt->generated)->toBeFalse();
    Event::assertNotDispatched(VideoGenerating::class);
    Event::assertNotDispatched(AudioGenerating::class);
    Http::assertSentCount(2);
});

test('Hailuo не возвращает успех без URL готового файла', /** @throws Exception */ function (): void {
    Http::fake(['*query/video_generation/*' => Http::response(['file_id' => 'file-1']), '*files/retrieve*' => Http::response([])]);
    expect(fn() => app(Hailuo::class)->check('video-1'))->toThrow(VideoGeneratingException::class);
    Http::assertSentCount(2);
});

test('Runway не возвращает успех без готового output', function (): void {
    Http::fake(['*tasks/video-1' => Http::response(['id' => 'video-1', 'status' => 'SUCCEEDED'])]);
    expect(/** @throws Exception */ fn() => app(Runway::class)->check('video-1'))->toThrow(VideoGeneratingException::class);
    Http::assertSentCount(1);
});
