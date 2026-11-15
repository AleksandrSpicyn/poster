<?php

declare(strict_types=1);

use App\Classes\Generators\ImageGenerator\ImageGenerator;
use App\Classes\Generators\PromptGenerator\PromptGenerator;
use App\Exceptions\CodexBusyException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

use function Tests\Support\fakeCodex;
use function Tests\Support\fakeCodexImages;

test('ошибка результата очищает каталог и разрешает следующий запрос', function (string $payload, string $exception): void {
    $attempt = 0;
    $workspace = '';
    fakeCodex(function (PendingProcess $process) use ($payload, &$attempt, &$workspace): ProcessResult {
        $workspace = $process->path ?? '';
        File::put($workspace . '/result.json', $attempt++ === 0 ? $payload : '{"text":"Recovered idea"}');

        return Process::result();
    });

    expect(fn() => app(PromptGenerator::class)->generate('Project brief'))->toThrow($exception);

    expect(File::exists($workspace))->toBeFalse();

    $text = app(PromptGenerator::class)->generate('Try again');

    expect($text)->toBe('Recovered idea');
    expect(File::exists($workspace))->toBeFalse();
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 2);
    Http::assertNothingSent();
})->with([
    'повреждённый JSON' => ['{broken', JsonException::class],
    'не объект' => ['null', RuntimeException::class],
    'список' => ['[]', RuntimeException::class],
    'нет текста' => ['{"other":"value"}', UnexpectedValueException::class],
    'пустой текст' => ['{"text":"  "}', UnexpectedValueException::class],
    'не строка' => ['{"text":42}', UnexpectedValueException::class],
    'большой результат' => [str_repeat('x', 1024 * 1024 + 1), RuntimeException::class],
]);

test('изображение и текст используют одну блокировку авторизации', function (): void {
    $disk = Storage::fake('public');
    fakeCodexImages(function (PendingProcess $process): ProcessResult {
        expect(fn() => app(PromptGenerator::class)->generate('Parallel idea'))->toThrow(CodexBusyException::class, 'already running');

        $imagePath = ($process->path ?? '') . '/image.png';
        File::copy(base_path('tests/Fixtures/portrait.png'), $imagePath);
        File::put(($process->path ?? '') . '/result.json', json_encode(['image_path' => $imagePath], JSON_THROW_ON_ERROR));

        return Process::result();
    });

    app(ImageGenerator::class)->generate('A fox');

    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    $disk->assertCount('generated-images', 1);
    Http::assertNothingSent();
});

test('не запускает CLI с некорректным таймаутом', function (): void {
    fakeCodex(fn(PendingProcess $process): ProcessResult => Process::result());
    config(['services.codex.timeout' => 0]);

    expect(fn() => app(PromptGenerator::class)->generate('Project brief'))->toThrow(InvalidArgumentException::class, 'timeout');

    Process::assertNothingRan();
    Http::assertNothingSent();
});
