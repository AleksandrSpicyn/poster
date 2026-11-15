<?php

declare(strict_types=1);

use App\Classes\Generators\ImageGenerator\ImageGenerator;
use App\Data\ImageReferenceData;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Image as ReferenceImage;
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
    $lock = Cache::lock('codex:' . hash('sha256', rtrim(config()->string('services.codex.home'), '/')), 330);
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

    expect(fn() => app(ImageGenerator::class)->generate('A fox'))->toThrow(RuntimeException::class, 'no result');

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

test('передаёт несколько референсов через SDK и приватные копии CLI', function (): void {
    $disk = Storage::fake('public');
    $workspace = '';
    fakeCodexImages(function (PendingProcess $process) use (&$workspace): ProcessResult {
        $workspace = $process->path ?? '';
        expect($process->command)->toContain('--image', $workspace . '/references/reference-1.png', $workspace . '/references/reference-2.png');
        expect($process->input)->toContain('referenced_image_paths', 'character-1.front', 'character-1.profile');
        expect(File::get($workspace . '/references/reference-1.png'))->toBe(File::get(base_path('tests/Fixtures/portrait.png')));
        expect(File::get($workspace . '/references/reference-2.png'))->toBe(File::get(base_path('tests/Fixtures/landscape.png')));
        File::copy(base_path('tests/Fixtures/portrait.png'), $workspace . '/image.png');
        File::put($workspace . '/result.json', json_encode(['image_path' => $workspace . '/image.png'], JSON_THROW_ON_ERROR));

        return Process::result();
    });

    $url = app(ImageGenerator::class)->generate('Two views of the same hero', [
        new ImageReferenceData(base_path('tests/Fixtures/portrait.png'), 'character-1.front'),
        new ImageReferenceData(base_path('tests/Fixtures/landscape.png'), 'character-1.profile'),
    ]);

    $disk->assertCount('generated-images', 1);
    expect($url)->toBe($disk->url($disk->allFiles('generated-images')[0]));
    expect(File::exists($workspace))->toBeFalse();
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
});

test('читает референс из приватного хранилища и удаляет временную копию', function (): void {
    Storage::fake('public');
    $private = Storage::fake('local');
    $private->put('references/hero.png', File::get(base_path('tests/Fixtures/portrait.png')));

    $workspace = '';
    fakeCodexImages(function (PendingProcess $process) use (&$workspace): ProcessResult {
        $workspace = $process->path ?? '';
        expect(File::exists($workspace . '/references/reference-1.png'))->toBeTrue();
        File::copy(base_path('tests/Fixtures/portrait.png'), $workspace . '/image.png');
        File::put($workspace . '/result.json', json_encode(['image_path' => $workspace . '/image.png'], JSON_THROW_ON_ERROR));

        return Process::result();
    });

    Image::of('Continue the hero')
        ->attachments([ReferenceImage::fromStorage('references/hero.png', 'local')->as('hero.front')])
        ->size('9:16')
        ->generate(provider: 'codex');

    expect(File::exists($workspace))->toBeFalse();
    $private->assertExists('references/hero.png');
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
});

test('не скачивает удалённый референс', function (): void {
    fakeCodexImages();

    expect(fn(): ImageResponse => Image::of('Hero')
        ->attachments([ReferenceImage::fromUrl('https://example.com/hero.png')])
        ->size('9:16')
        ->generate(provider: 'codex'))->toThrow(InvalidArgumentException::class, 'local or stored');

    Process::assertNothingRan();
});

test('отклоняет повреждённый локальный референс и освобождает блокировку', function (): void {
    $disk = Storage::fake('public');
    fakeCodexImages();
    $disk->put('bad.png', 'not an image');

    expect(fn() => app(ImageGenerator::class)->generate('Hero', [new ImageReferenceData($disk->path('bad.png'), 'front')]))
        ->toThrow(InvalidArgumentException::class, 'valid PNG');

    Process::assertNothingRan();

    app(ImageGenerator::class)->generate('Hero');

    $disk->assertCount('generated-images', 1);
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
});

test('ограничивает число референсов до запуска CLI', function (): void {
    fakeCodexImages();

    expect(fn(): ImageResponse => Image::of('Hero')
        ->attachments(array_fill(0, 11, ReferenceImage::fromPath(base_path('tests/Fixtures/portrait.png'))))
        ->size('9:16')
        ->generate(provider: 'codex'))->toThrow(InvalidArgumentException::class, 'at most ten');

    Process::assertNothingRan();
});

test('отклоняет слишком большой референс до чтения и запуска CLI', function (): void {
    $disk = Storage::fake('local');
    fakeCodexImages();
    $stream = fopen($disk->path('oversized.png'), 'wb');

    if ($stream === false) {
        throw new RuntimeException('Could not create the test reference image.');
    }

    ftruncate($stream, 20 * 1024 * 1024 + 1);
    fclose($stream);

    expect(fn() => app(ImageGenerator::class)->generate('Hero', [new ImageReferenceData($disk->path('oversized.png'), 'front')]))
        ->toThrow(InvalidArgumentException::class, 'size limit');

    Process::assertNothingRan();
});

test('не принимает входной референс за сгенерированный кадр', function (): void {
    $disk = Storage::fake('public');
    fakeCodexImages(function (PendingProcess $process): ProcessResult {
        File::put(($process->path ?? '') . '/result.json', json_encode(['image_path' => ($process->path ?? '') . '/references/reference-1.png'], JSON_THROW_ON_ERROR));

        return Process::result();
    });

    expect(fn() => app(ImageGenerator::class)->generate('Hero', [new ImageReferenceData(base_path('tests/Fixtures/portrait.png'), 'front')]))
        ->toThrow(RuntimeException::class, 'invalid image path');

    $disk->assertEmpty();
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
});
