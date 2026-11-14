<?php

declare(strict_types=1);

use App\Classes\Generators\VideoGenerator\Hailuo\Hailuo;
use App\Exceptions\VideoGeneratingException;
use App\Settings\GeneralSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(/** @throws Exception */ function (): void {
    config([
        'services.hailuo.api_url' => 'https://api.minimax.io/v1',
        'services.hailuo.api_key' => 'test', 'services.hailuo.group_id' => 'group',
        'services.runway.api_key' => 'test', 'services.suno_unofficial.api_key' => 'test',
    ]);

    GeneralSettings::fake(['duration' => 10]);
});

test('Hailuo возвращает только готовое видео', /** @throws Exception */ function (array $response, ?string $expected): void {
    Http::fake([
        '*query/video_generation/*' => Http::response($response),
        '*files/retrieve*' => Http::response(['file' => ['download_url' => 'https://cdn.example.com/video.mp4']]),
    ]);

    expect(app(Hailuo::class)->check('video-1'))->toBe($expected);
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), 'task_id=video-1'));
})->with([[[], null], [['file_id' => 'file-1'], 'https://cdn.example.com/video.mp4']]);

test('Hailuo не возвращает успех без URL готового файла', /** @throws Exception */ function (): void {
    Http::fake(['*query/video_generation/*' => Http::response(['file_id' => 'file-1']), '*files/retrieve*' => Http::response([])]);

    expect(fn() => app(Hailuo::class)->check('video-1'))->toThrow(VideoGeneratingException::class);
    Http::assertSentCount(2);
});

test('ошибка генератора не маскируется успешным результатом', /** @throws Exception */ function (): void {
    Http::fake(['https://api.minimax.io/v1/*' => Http::response(['message' => 'Unavailable'], 503)]);

    $client = app(Hailuo::class);

    expect(fn() => $client->generate('image', 'idea'))->toThrow(RuntimeException::class);
    Http::assertSentCount(1);
});

test('генератор отклоняет успешный HTTP с отсутствующим идентификатором', /** @throws Exception */ function (): void {
    config(['services.hailuo.api_key' => 'test', 'services.hailuo.group_id' => 'test', 'services.runway.api_key' => 'test', 'services.suno_unofficial.api_key' => 'test']);

    GeneralSettings::fake(['duration' => 10]);
    Http::fake(['https://api.minimax.io/v1/*' => Http::response([])]);

    $client = app(Hailuo::class);

    expect(fn() => $client->generate('image', 'idea'))->toThrow(UnexpectedValueException::class);
    Http::assertSentCount(1);
});
