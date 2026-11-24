<?php

declare(strict_types=1);

use App\Classes\Generators\VideoGenerator\Seedance\Enums\Model;
use App\Classes\Generators\VideoGenerator\Seedance\Seedance;
use App\Data\VideoGenerationRequestData;
use App\Enums\VideoInputMode;
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

    $id = app(Seedance::class)->generate(seedanceVideoRequest('https://cdn.example.com/frame.png', 'A fox jumps'));

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
            'return_last_frame' => true,
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
    'нет метаданных звука' => [['status' => 'succeeded'], VideoGeneratingException::class],
    'нет URL' => [['status' => 'succeeded', 'generate_audio' => true], UnexpectedValueException::class],
]);

test('Seedance проверяет длительность и разрешение до платного запроса', function (Model $model, string $resolution, int $duration): void {
    config(['services.seedance.model' => $model->value, 'services.seedance.resolution' => $resolution]);
    GeneralSettings::fake(['duration' => $duration]);
    Http::fake();

    expect(fn() => app(Seedance::class)->generate(seedanceVideoRequest()))->toThrow(InvalidArgumentException::class);
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

    expect(app(Seedance::class)->generate(seedanceVideoRequest()))->toBe('video-1');
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

    expect(fn() => app(Seedance::class)->generate(seedanceVideoRequest()))->toThrow(VideoGenerationUncertainException::class);
    Http::assertSentCount(1);
});

test('Seedance отклоняет ответ запуска без ID', function (): void {
    Http::fake(['*contents/generations/tasks' => Http::response([])]);

    expect(fn() => app(Seedance::class)->generate(seedanceVideoRequest()))->toThrow(VideoGenerationUncertainException::class);
    Http::assertSentCount(1);
});

test('Seedance требует API ключ', function (): void {
    config(['services.seedance.api_key' => '']);
    Http::fake();

    expect(fn() => app(Seedance::class)->generate(seedanceVideoRequest()))->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});

test('локальный первый кадр отправляется напрямую без внешнего URL', function (): void {
    $disk = Storage::fake('public');
    $bytes = File::get(base_path('tests/Fixtures/portrait.png'));
    $disk->put('generated-images/frame.png', $bytes);
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'video-1'])]);

    expect(app(Seedance::class)->generate(seedanceVideoRequest($disk->url('generated-images/frame.png'))))->toBe('video-1');

    Http::assertSent(fn(Request $request): bool => data_get($request->data(), 'content.1.image_url.url') === 'data:image/png;base64,' . base64_encode($bytes));
    Http::assertSentCount(1);
});

test('пустой локальный кадр отклоняется до платного запроса', function (): void {
    $disk = Storage::fake('public');
    $disk->put('generated-images/empty.png', '');
    Http::fake();

    expect(fn() => app(Seedance::class)->generate(seedanceVideoRequest($disk->url('generated-images/empty.png'))))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

function seedanceVideoRequest(string $imageUrl = 'image', string $prompt = 'idea'): VideoGenerationRequestData
{
    return new VideoGenerationRequestData(
        prompt: $prompt,
        duration: app(GeneralSettings::class)->duration,
        model: config()->string('services.seedance.model'),
        firstFrameUrl: $imageUrl,
        resolution: config()->string('services.seedance.resolution'),
    );
}

test('Seedance передаёт референсы отдельно от первого кадра', function (): void {
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'references-1'])]);
    $request = new VideoGenerationRequestData(
        prompt: 'The person from Image 1 greets the person from Image 2.',
        duration: 8,
        model: Model::V2_5->value,
        mode: VideoInputMode::REFERENCES,
        referenceImageUrls: ['https://cdn.example.com/front.png', 'https://cdn.example.com/profile.png'],
        aspectRatio: '9:16',
    );

    $id = app(Seedance::class)->generate($request);

    expect($id)->toBe('references-1');
    Http::assertSent(fn(Request $http): bool => $http['omni_reference_task_type'] === 'reference'
        && $http['ratio'] === '9:16'
        && data_get($http->data(), 'content.1.role') === 'reference_image'
        && data_get($http->data(), 'content.2.image_url.url') === 'https://cdn.example.com/profile.png');
    Http::assertSentCount(1);
});

test('Seedance продолжает видео с явным режимом и без нативного звука', function (): void {
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'extend-1'])]);
    $request = new VideoGenerationRequestData(
        prompt: 'Continue @Video 1: the character opens the door.',
        duration: 8,
        model: Model::V2_5->value,
        mode: VideoInputMode::EXTEND,
        referenceImageUrls: ['https://cdn.example.com/character.png'],
        sourceVideoUrl: 'https://cdn.example.com/previous.mp4',
        generateAudio: false,
    );

    app(Seedance::class)->generate($request);

    Http::assertSent(fn(Request $http): bool => $http['omni_reference_task_type'] === 'extend'
        && $http['ratio'] === 'adaptive'
        && $http['generate_audio'] === false
        && data_get($http->data(), 'content.2.role') === 'reference_video'
        && data_get($http->data(), 'content.2.video_url.url') === 'https://cdn.example.com/previous.mp4');
    Http::assertSentCount(1);
});

test('несовместимые референсы отклоняются без внешнего запроса', function (VideoGenerationRequestData $request): void {
    Http::fake();

    expect(fn() => app(Seedance::class)->generate($request))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'первый кадр вместе с референсом' => new VideoGenerationRequestData(prompt: 'idea', duration: 8, model: Model::V2_5->value, firstFrameUrl: 'frame.png', referenceImageUrls: ['face.png']),
    'продолжение на старой модели' => new VideoGenerationRequestData(prompt: 'Continue video', duration: 8, model: Model::V2_0_MINI->value, mode: VideoInputMode::EXTEND, sourceVideoUrl: 'previous.mp4'),
    'слишком много ракурсов' => new VideoGenerationRequestData(prompt: 'idea', duration: 8, model: Model::V2_0_MINI->value, mode: VideoInputMode::REFERENCES, referenceImageUrls: array_fill(0, 10, 'face.png')),
    'фиксированный размер продолжения' => new VideoGenerationRequestData(prompt: 'Continue video', duration: 8, model: Model::V2_5->value, mode: VideoInputMode::EXTEND, sourceVideoUrl: 'previous.mp4', aspectRatio: '9:16'),
]);

test('Seedance использует снимок параметров после смены конфигурации', function (): void {
    $request = new VideoGenerationRequestData(prompt: 'idea', duration: 8, model: Model::V2_0_MINI->value, firstFrameUrl: 'frame.png');
    config(['services.seedance.model' => Model::V2_5->value, 'services.seedance.resolution' => '1080p']);
    GeneralSettings::fake(['duration' => 30]);
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'frozen-1'])]);

    app(Seedance::class)->generate($request);

    Http::assertSent(fn(Request $http): bool => $http['model'] === Model::V2_0_MINI->value
        && $http['duration'] === 8 && $http['resolution'] === '480p');
    Http::assertSentCount(1);
});

test('Seedance возвращает последний кадр и метаданные немого результата', function (): void {
    Http::fake(['*contents/generations/tasks/silent-1' => Http::response([
        'status' => 'succeeded',
        'generate_audio' => false,
        'duration' => 8,
        'model' => Model::V2_5->value,
        'content' => ['video_url' => 'https://cdn.example.com/silent.mp4', 'last_frame_url' => 'https://cdn.example.com/last.jpg'],
    ])]);

    $result = app(Seedance::class)->check('silent-1');

    expect($result?->videoUrl)->toBe('https://cdn.example.com/silent.mp4');
    expect($result?->audioUrl)->toBeNull();
    expect($result?->lastFrameUrl)->toBe('https://cdn.example.com/last.jpg');
    expect($result?->duration)->toBe(8);
    expect($result?->model)->toBe(Model::V2_5->value);
    Http::assertSentCount(1);
});
