<?php

declare(strict_types=1);

use App\Classes\Generators\ImageGenerator\ImageGenerator;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\ImagePrompt;
use Laravel\Ai\Responses\ImageResponse;

use function Tests\Support\fakeCodexImages;

test('сохраняет кадр и возвращает URL без платного API', function (): void {
    $disk = Storage::fake('public');
    fakeCodexImages();
    $prompt = 'A fox by a fountain; $(do-not-run) `not-a-command`';

    $url = app(ImageGenerator::class)->generate($prompt);

    $files = $disk->allFiles('generated-images');
    expect($files)->toHaveCount(1);
    expect($url)->toBe($disk->url($files[0]));
    expect($disk->get($files[0]))->toBe(File::get(base_path('tests/Fixtures/portrait.png')));
    Process::assertRan(fn(PendingProcess $process): bool => is_array($process->command)
        && in_array('forced_login_method="chatgpt"', $process->command, true)
        && in_array('--ignore-user-config', $process->command, true)
        && in_array('features.shell_tool=false', $process->command, true)
        && in_array('--output-schema', $process->command, true)
        && $process->timeout === 300
        && $process->environment['OPENAI_API_KEY'] === false
        && $process->environment['CODEX_API_KEY'] === false
        && is_string($process->input) && str_contains($process->input, json_encode($prompt, JSON_THROW_ON_ERROR))
        && $process->path !== null && ! File::exists($process->path));
});

test('требует отдельный вход ChatGPT', function (): void {
    $disk = Storage::fake('public');
    fakeCodexImages();
    Storage::disk('codex')->delete('auth.json');

    expect(fn() => app(ImageGenerator::class)->generate('A fox'))->toThrow(RuntimeException::class, 'login is missing');

    Process::assertNothingRan();
    $disk->assertEmpty();
});

test('не запускает параллельную генерацию с одной авторизацией', function (): void {
    $disk = Storage::fake('public');
    fakeCodexImages();
    $lock = Cache::lock('codex-images:' . hash('sha256', config()->string('services.codex.home')), 330);
    $lock->get();

    try {
        expect(fn() => app(ImageGenerator::class)->generate('A fox'))->toThrow(RuntimeException::class, 'already running');

        Process::assertNothingRan();
        $disk->assertEmpty();
    } finally {
        $lock->release();
    }
});

test('отказ Codex очищает каталог и позволяет повторить генерацию', function (): void {
    $disk = Storage::fake('public');
    $workspace = '';
    fakeCodexImages(function (PendingProcess $process) use (&$workspace): ProcessResult {
        $workspace = $process->path ?? '';

        return Process::result(errorOutput: 'Usage limit reached', exitCode: 1);
    });

    expect(fn() => app(ImageGenerator::class)->generate('A fox'))->toThrow(RuntimeException::class, 'generation failed');

    expect(File::exists($workspace))->toBeFalse();
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    $disk->assertEmpty();

    fakeCodexImages();

    app(ImageGenerator::class)->generate('Another fox');

    $disk->assertCount('generated-images', 1);
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 2);
});

test('использует стандартный fake Laravel AI', function (): void {
    $disk = Storage::fake('public');
    Process::fake();
    Image::fake([base64_encode(File::get(base_path('tests/Fixtures/portrait.png')))])->preventStrayImages();

    $url = app(ImageGenerator::class)->generate('A fox');

    Image::assertGenerated(fn(ImagePrompt $prompt): bool => $prompt->prompt === 'A fox'
        && $prompt->size === '9:16' && $prompt->provider->name() === 'codex');
    Process::assertNothingRan();
    $disk->assertCount('generated-images', 1);
    expect($url)->toBe($disk->url($disk->allFiles('generated-images')[0]));
});

test('не обещает неподдерживаемую модель Codex', function (): void {
    fakeCodexImages();

    expect(fn(): ImageResponse => Image::of('A fox')->size('9:16')->generate(provider: 'codex', model: 'another-image-model'))
        ->toThrow(InvalidArgumentException::class);

    Process::assertNothingRan();
});

test('читает сохранённый кадр из отдельного CODEX_HOME', function (): void {
    $disk = Storage::fake('public');
    fakeCodexImages(function (PendingProcess $process): ProcessResult {
        $path = Storage::disk('codex')->path('generated_images/session/image.png');
        File::ensureDirectoryExists(dirname($path));
        File::copy(base_path('tests/Fixtures/portrait.png'), $path);
        File::put(($process->path ?? '') . '/result.json', json_encode(['image_path' => $path], JSON_THROW_ON_ERROR));

        return Process::result();
    });

    app(ImageGenerator::class)->generate('A fox');

    $disk->assertCount('generated-images', 1);
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    Storage::disk('codex')->assertExists('generated_images/session/image.png');
});

test('не сохраняет ответ без изображения', function (): void {
    $disk = Storage::fake('public');
    fakeCodexImages(fn(PendingProcess $process): ProcessResult => Process::result());

    expect(fn() => app(ImageGenerator::class)->generate('A fox'))->toThrow(RuntimeException::class, 'no image result');

    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    $disk->assertEmpty();
});

test('не читает файл вне каталогов генерации', function (): void {
    $disk = Storage::fake('public');
    fakeCodexImages(function (PendingProcess $process): ProcessResult {
        File::put(($process->path ?? '') . '/result.json', json_encode(['image_path' => base_path('tests/Fixtures/portrait.png')], JSON_THROW_ON_ERROR));

        return Process::result();
    });

    expect(fn() => app(ImageGenerator::class)->generate('A fox'))->toThrow(RuntimeException::class, 'invalid image path');

    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    $disk->assertEmpty();
});

test('отклоняет неподходящий кадр', function (string $fixture): void {
    $disk = Storage::fake('public');
    fakeCodexImages(function (PendingProcess $process) use ($fixture): ProcessResult {
        $path = ($process->path ?? '') . '/image.png';
        File::copy(base_path('tests/Fixtures/' . $fixture), $path);
        File::put(($process->path ?? '') . '/result.json', json_encode(['image_path' => $path], JSON_THROW_ON_ERROR));

        return Process::result();
    });

    expect(fn() => app(ImageGenerator::class)->generate('A fox'))->toThrow(RuntimeException::class, 'portrait PNG');

    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    $disk->assertEmpty();
})->with(['горизонтальный' => 'landscape.png', 'слишком маленький' => 'small.png']);
