<?php

declare(strict_types=1);

use App\Classes\Media\Upscale\PollUnavailable;
use App\Classes\Media\Upscale\SubmissionUnknown;
use App\Classes\Media\Upscale\UpscaleRejected;
use App\Classes\Media\Upscale\VideoUpscaler;
use App\Classes\Media\Upscale\VideoUpscalerFactory;
use App\Data\Upscale\UpscaleReceiptData;
use App\Data\Upscale\UpscaleRequestData;
use App\Data\Upscale\UpscaleStatusData;
use App\Enums\UpscaleExport;
use App\Enums\UpscaleProvider;
use App\Enums\UpscaleQueueStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function falTestDriver(): VideoUpscaler
{
    config(['services.fal.key' => 'test-key']);

    return app(VideoUpscalerFactory::class)->make(UpscaleProvider::FAL);
}

function falTestReceipt(): UpscaleReceiptData
{
    return new UpscaleReceiptData('abc', 'https://queue.fal.run/task/abc/status', 'https://queue.fal.run/task/abc');
}

test('отправляет Proteus один раз и получает результат по квитанции', function (UpscaleExport $export): void {
    $receipt = falTestReceipt();
    $source = 'https://poster.example/input.mp4';
    $video = 'https://v3.fal.media/files/result.mp4';
    Http::fake([
        'https://queue.fal.run/topaz/upscale/video/precision' => Http::response([
            'request_id' => $receipt->id, 'status_url' => $receipt->statusUrl, 'response_url' => $receipt->resultUrl,
        ]),
        $receipt->statusUrl => Http::sequence()
            ->push(['status' => 'IN_QUEUE'])
            ->push(['status' => 'IN_PROGRESS'])
            ->push(['status' => 'COMPLETED']),
        $receipt->resultUrl => Http::response(['video' => ['url' => $video]]),
    ]);
    $driver = falTestDriver();

    $submitted = $driver->submit(new UpscaleRequestData($source, 300_000, $export));

    expect($submitted)->toEqual($receipt);
    expect($driver->check($submitted)->status)->toBe(UpscaleQueueStatus::QUEUED);
    expect($driver->check($submitted)->status)->toBe(UpscaleQueueStatus::PROCESSING);

    $result = $driver->check($submitted);
    expect($result->status)->toBe(UpscaleQueueStatus::READY)
        ->and($result->videoUrl)->toBe($video);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Key test-key')
        && $request->data() === ['video_url' => $source, 'model' => 'Proteus', 'upscale_factor' => $export->width() / 486]);
    Http::assertSentCount(5);
})->with(UpscaleExport::cases());

test('различает отказ и неопределённый исход отправки', function (int $status, string $exception): void {
    Http::fake(['*' => Http::response([], $status)]);
    $driver = falTestDriver();

    expect(fn(): UpscaleReceiptData => $driver->submit(new UpscaleRequestData('https://poster.example/input', 1000, UpscaleExport::HD)))
        ->toThrow($exception);
    Http::assertSentCount(1);
})->with([[422, UpscaleRejected::class], [503, SubmissionUnknown::class], [200, SubmissionUnknown::class]]);

test('проверяет длительность до отправки', function (): void {
    $driver = falTestDriver();

    expect(fn(): UpscaleReceiptData => $driver->submit(new UpscaleRequestData('https://poster.example/input', 300_001, UpscaleExport::HD)))
        ->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

test('проверяет адрес очереди перед передачей ключа', function (): void {
    $driver = falTestDriver();

    expect(fn(): UpscaleStatusData => $driver->check(new UpscaleReceiptData('abc', 'https://elsewhere.example/status', falTestReceipt()->resultUrl)))
        ->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

test('отличает временный сбой чтения от ошибки исполнения', function (int $status, string $exception): void {
    $receipt = falTestReceipt();
    Http::fake([
        $receipt->statusUrl => Http::response(['status' => 'COMPLETED']),
        $receipt->resultUrl => Http::response(['error' => 'failed'], $status),
    ]);

    expect(fn(): UpscaleStatusData => falTestDriver()->check($receipt))->toThrow($exception);
    Http::assertSentCount(2);
})->with([[503, PollUnavailable::class], [422, UpscaleRejected::class], [200, UpscaleRejected::class]]);

test('потеря соединения при отправке требует сверки квитанции', function (): void {
    Http::fake(['*' => Http::failedConnection()]);
    $driver = falTestDriver();

    expect(fn(): UpscaleReceiptData => $driver->submit(new UpscaleRequestData('https://poster.example/input', 1000, UpscaleExport::HD)))
        ->toThrow(SubmissionUnknown::class);
});

test('потеря соединения при опросе разрешает повтор чтения', function (): void {
    Http::fake(['*' => Http::failedConnection()]);
    $driver = falTestDriver();

    expect(fn(): UpscaleStatusData => $driver->check(falTestReceipt()))->toThrow(PollUnavailable::class);
});

test('проверяет регистрацию драйвера до отправки', function (): void {
    config(['services.upscale.drivers' => ['fal' => stdClass::class]]);

    $factory = app(VideoUpscalerFactory::class);

    expect($factory->primary())->toBe(UpscaleProvider::FAL);
    expect(fn() => $factory->make($factory->primary()))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});
