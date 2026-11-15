<?php

declare(strict_types=1);

use App\Enums\VideoProvider;
use App\Exceptions\VideoGenerationUncertainException;
use App\Settings\GeneralSettings;
use App\Tasks\CreateVideo;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.video.provider' => 'seedance',
        'services.video.fallback_provider' => 'runway',
        'services.seedance.api_key' => 'test',
    ]);
    GeneralSettings::fake(['duration' => 10]);
});

test('сохраняется фактический провайдер Seedance', function (): void {
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'seedance-1'])]);

    $video = app(CreateVideo::class)->run('https://example.test/frame.png', 'A fox jumps');

    expect($video->id)->toBe('seedance-1');
    expect($video->provider)->toBe(VideoProvider::SEEDANCE);
    Http::assertSentCount(1);
});

test('явный отказ запуска переключает генерацию на резервного провайдера', function (): void {
    Http::fake([
        '*contents/generations/tasks' => Http::response([], 429),
        '*image_to_video' => Http::response(['id' => 'runway-1']),
    ]);

    $video = app(CreateVideo::class)->run('https://example.test/frame.png', 'A fox jumps');

    expect($video->id)->toBe('runway-1');
    expect($video->provider)->toBe(VideoProvider::RUNWAY);
    Http::assertSentCount(2);
});

test('таймаут запуска Seedance не создаёт вторую платную задачу', function (): void {
    Http::fake(['*contents/generations/tasks' => Http::failedConnection()]);

    expect(fn() => app(CreateVideo::class)->run('image', 'idea'))->toThrow(VideoGenerationUncertainException::class);
    Http::assertNotSent(fn(Request $request): bool => str_contains($request->url(), 'image_to_video'));
});

test('неоднозначный ответ Seedance не запускает резервную генерацию', function (int $status, string $exception): void {
    Http::fake(['*contents/generations/tasks' => Http::response([], $status)]);

    expect(fn() => app(CreateVideo::class)->run('image', 'idea'))->toThrow($exception);
    Http::assertSentCount(1);
})->with([
    'ошибка сервера' => [503, VideoGenerationUncertainException::class],
    'успех без ID' => [200, VideoGenerationUncertainException::class],
]);

test('один и тот же провайдер не запускается повторно при отказе', function (): void {
    config(['services.video.fallback_provider' => 'seedance']);
    Http::fake(['*contents/generations/tasks' => Http::response([], 429)]);

    expect(fn() => app(CreateVideo::class)->run('image', 'idea'))->toThrow(RequestException::class);
    Http::assertSentCount(1);
});
