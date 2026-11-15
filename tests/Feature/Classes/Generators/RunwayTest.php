<?php

declare(strict_types=1);

use App\Classes\Generators\VideoGenerator\Runway\Runway;
use App\Data\VideoGenerationRequestData;
use App\Enums\VideoInputMode;
use App\Exceptions\VideoGeneratingException;
use App\Exceptions\VideoGenerationUncertainException;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Http;

beforeEach(/** @throws Exception */ function (): void {
    config([
        'services.runway.api_url' => 'https://api.dev.runwayml.com/v1',
        'services.hailuo.api_key' => 'test', 'services.hailuo.group_id' => 'group',
        'services.runway.api_key' => 'test', 'services.suno_unofficial.api_key' => 'test',
    ]);

    GeneralSettings::fake(['duration' => 10]);
});

test('Runway возвращает результат только завершённого задания', /** @throws Exception */ function (string $status, ?string $expected): void {
    Http::fake(['*tasks/video-1' => Http::response(['id' => 'video-1', 'status' => $status, 'output' => ['https://cdn.example.com/video.mp4']])]);

    expect(app(Runway::class)->check('video-1')?->videoUrl)->toBe($expected);

    Http::assertSentCount(1);
})->with(['ожидание' => ['PENDING', null], 'готово' => ['SUCCEEDED', 'https://cdn.example.com/video.mp4']]);

test('Runway сообщает об отказе задания', function (): void {
    Http::fake(['*tasks/video-1' => Http::response(['id' => 'video-1', 'status' => 'FAILED', 'failure' => 'Failed'])]);

    expect(/** @throws Exception */ fn() => app(Runway::class)->check('video-1'))->toThrow(VideoGeneratingException::class);

    Http::assertSentCount(1);
});

test('Runway не возвращает успех без готового output', function (): void {
    Http::fake(['*tasks/video-1' => Http::response(['id' => 'video-1', 'status' => 'SUCCEEDED'])]);

    expect(/** @throws Exception */ fn() => app(Runway::class)->check('video-1'))->toThrow(VideoGeneratingException::class);
    Http::assertSentCount(1);
});

test('ошибка генератора не маскируется успешным результатом', /** @throws Exception */ function (): void {
    Http::fake(['https://api.dev.runwayml.com/v1/*' => Http::response(['message' => 'Unavailable'], 503)]);

    $client = app(Runway::class);

    expect(fn() => $client->generate(new VideoGenerationRequestData(prompt: 'idea', duration: 10, model: 'gen4_turbo', firstFrameUrl: 'image', resolution: '720p', aspectRatio: '9:16', returnLastFrame: false, generateAudio: false)))->toThrow(VideoGenerationUncertainException::class);
    Http::assertSentCount(1);
});

test('генератор отклоняет успешный HTTP с отсутствующим идентификатором', /** @throws Exception */ function (): void {
    config(['services.hailuo.api_key' => 'test', 'services.hailuo.group_id' => 'test', 'services.runway.api_key' => 'test', 'services.suno_unofficial.api_key' => 'test']);

    GeneralSettings::fake(['duration' => 10]);
    Http::fake(['https://api.dev.runwayml.com/v1/*' => Http::response([])]);

    $client = app(Runway::class);

    expect(fn() => $client->generate(new VideoGenerationRequestData(prompt: 'idea', duration: 10, model: 'gen4_turbo', firstFrameUrl: 'image', resolution: '720p', aspectRatio: '9:16', returnLastFrame: false, generateAudio: false)))->toThrow(VideoGenerationUncertainException::class);
    Http::assertSentCount(1);
});

test('Runway отклоняет неподдерживаемые референсы до запроса', function (): void {
    Http::fake();
    $request = new VideoGenerationRequestData(prompt: 'idea', duration: 10, model: 'gen4_turbo', mode: VideoInputMode::REFERENCES, referenceImageUrls: ['face.png'], aspectRatio: '9:16', returnLastFrame: false, generateAudio: false);

    expect(fn() => app(Runway::class)->generate($request))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});
