<?php

declare(strict_types=1);

use App\Classes\Generators\VideoGenerator\Seedance\Enums\Model;
use App\Classes\Generators\VideoGenerator\Seedance\Seedance;
use App\Exceptions\VideoGeneratingException;
use App\Exceptions\VideoGenerationUncertainException;
use App\Settings\GeneralSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config([
        'services.seedance.api_url' => 'https://ark.ap-southeast.bytepluses.com/api/v3',
        'services.seedance.api_key' => 'test-seedance',
        'services.seedance.model' => Model::V2_5_FLASH->value,
        'services.seedance.resolution' => '720p',
    ]);

    GeneralSettings::fake(['duration' => 10]);
});

test('Seedance отправляет первый кадр и запрашивает видео со звуком', function (Model $model): void {
    config(['services.seedance.model' => $model->value]);
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'seedance-1'])]);

    $id = app(Seedance::class)->generate('https://cdn.example.com/frame.png', 'A fox jumps');

    expect($id)->toBe('seedance-1');
    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://ark.ap-southeast.bytepluses.com/api/v3/contents/generations/tasks'
        && $request->hasHeader('Authorization', 'Bearer test-seedance')
        && $request->data() === [
            'model' => $model->value,
            'content' => [
                ['type' => 'text', 'text' => 'A fox jumps'],
                ['type' => 'image_url', 'image_url' => ['url' => 'https://cdn.example.com/frame.png'], 'role' => 'first_frame'],
            ],
            'resolution' => '720p',
            'ratio' => 'adaptive',
            'duration' => 10,
            'generate_audio' => true,
            'watermark' => false,
            'execution_expires_after' => 3600,
        ]);
    Http::assertSentCount(1);
})->with(['2.5 Flash' => Model::V2_5_FLASH, '2.0 Mini' => Model::V2_0_MINI]);

test('Seedance возвращает URL только готового видео со звуком', function (string $status, ?string $expected): void {
    Http::fake(['*contents/generations/tasks/seedance-1' => Http::response([
        'status' => $status,
        'generate_audio' => true,
        'content' => ['video_url' => 'https://cdn.example.com/video.mp4'],
    ])]);

    $result = app(Seedance::class)->check('seedance-1');

    expect($result?->videoUrl)->toBe($expected);
    expect($result?->audioUrl)->toBe($expected);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET');
})->with([
    'в очереди' => ['queued', null],
    'генерация' => ['running', null],
    'готово' => ['succeeded', 'https://cdn.example.com/video.mp4'],
]);

test('Seedance завершает отказавшую задачу ошибкой', function (string $status): void {
    Http::fake(['*contents/generations/tasks/*' => Http::response(['status' => $status])]);

    expect(fn() => app(Seedance::class)->check('seedance-1'))->toThrow(VideoGeneratingException::class);
})->with(['failed', 'expired', 'cancelled']);

test('Seedance отклоняет неполный или неизвестный ответ', function (array $payload, string $exception): void {
    Http::fake(['*contents/generations/tasks/*' => Http::response($payload)]);

    expect(fn() => app(Seedance::class)->check('seedance-1'))->toThrow($exception);
})->with([
    'нет статуса' => [[], UnexpectedValueException::class],
    'новый статус' => [['status' => 'unknown'], ValueError::class],
    'нет звука' => [['status' => 'succeeded', 'generate_audio' => false], VideoGeneratingException::class],
    'нет URL' => [['status' => 'succeeded', 'generate_audio' => true], UnexpectedValueException::class],
]);

test('Seedance проверяет длительность и разрешение до платного запроса', function (Model $model, string $resolution, int $duration): void {
    config(['services.seedance.model' => $model->value, 'services.seedance.resolution' => $resolution]);
    GeneralSettings::fake(['duration' => $duration]);
    Http::fake();

    expect(fn() => app(Seedance::class)->generate('image', 'idea'))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
})->with([
    'слишком короткое' => [Model::V2_5_FLASH, '720p', 3],
    'слишком длинное' => [Model::V2_5, '720p', 31],
    'лимит 2.0' => [Model::V2_0, '720p', 16],
    'Flash без 1080p' => [Model::V2_5_FLASH, '1080p', 10],
    'Mini без 1080p' => [Model::V2_0_MINI, '1080p', 10],
    'Fast без 4k' => [Model::V2_0_FAST, '4k', 10],
    '2.5 без 4k' => [Model::V2_5, '4k', 10],
]);

test('Seedance принимает граничную длительность поддерживаемых моделей', function (Model $model, string $resolution, int $duration): void {
    config(['services.seedance.model' => $model->value, 'services.seedance.resolution' => $resolution]);
    GeneralSettings::fake(['duration' => $duration]);
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'video-1'])]);

    expect(app(Seedance::class)->generate('image', 'idea'))->toBe('video-1');
    Http::assertSent(fn(Request $request): bool => $request['duration'] === $duration && $request['resolution'] === $resolution);
})->with([
    'минимум' => [Model::V2_5_FLASH, '480p', 4],
    'максимум Flash' => [Model::V2_5_FLASH, '720p', 30],
    'максимум 2.5' => [Model::V2_5, '1080p', 30],
    '2.0 4k' => [Model::V2_0, '4k', 15],
    'Fast' => [Model::V2_0_FAST, '720p', 15],
    'Mini' => [Model::V2_0_MINI, '480p', 15],
]);

test('Seedance не повторяет запуск при HTTP ошибке', function (): void {
    Http::fake(['*contents/generations/tasks' => Http::response([], 503)]);

    expect(fn() => app(Seedance::class)->generate('image', 'idea'))->toThrow(VideoGenerationUncertainException::class);
    Http::assertSentCount(1);
});

test('Seedance отклоняет ответ запуска без ID', function (): void {
    Http::fake(['*contents/generations/tasks' => Http::response([])]);

    expect(fn() => app(Seedance::class)->generate('image', 'idea'))->toThrow(VideoGenerationUncertainException::class);
    Http::assertSentCount(1);
});

test('Seedance требует API ключ', function (): void {
    config(['services.seedance.api_key' => '']);
    Http::fake();

    expect(fn() => app(Seedance::class)->generate('image', 'idea'))->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});

test('локальный первый кадр отправляется напрямую без внешнего URL', function (): void {
    $disk = Storage::fake('public');
    $bytes = File::get(base_path('tests/Fixtures/portrait.png'));
    $disk->put('generated-images/frame.png', $bytes);
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'video-1'])]);

    expect(app(Seedance::class)->generate($disk->url('generated-images/frame.png'), 'idea'))->toBe('video-1');

    Http::assertSent(fn(Request $request): bool => data_get($request->data(), 'content.1.image_url.url') === 'data:image/png;base64,' . base64_encode($bytes));
    Http::assertSentCount(1);
});

test('пустой локальный кадр отклоняется до платного запроса', function (): void {
    $disk = Storage::fake('public');
    $disk->put('generated-images/empty.png', '');
    Http::fake();

    expect(fn() => app(Seedance::class)->generate($disk->url('generated-images/empty.png'), 'idea'))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});
