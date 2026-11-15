<?php

declare(strict_types=1);

use App\Classes\Generators\VideoGenerator\VideoGenerator;
use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Data\VideoResultData;
use App\Enums\VideoProvider;

use function Pest\Laravel\mock;

test('драйвер создаётся из регистрации без зависимости фабрики от его класса', function (): void {
    config(['services.video.drivers.hailuo' => VideoGenerator::class]);
    mock(VideoGenerator::class)
        ->shouldReceive('check')
        ->with('task-1')
        ->once()
        ->andReturn(new VideoResultData('video.mp4', 'audio.mp3'));

    $result = app(VideoGeneratorFactory::class)->make(VideoProvider::HAILUO)->check('task-1');

    expect($result?->videoUrl)->toBe('video.mp4');
    expect($result?->audioUrl)->toBe('audio.mp3');
});

test('незарегистрированный драйвер отклоняется явно', function (): void {
    config(['services.video.drivers' => []]);

    expect(fn() => app(VideoGeneratorFactory::class)->make(VideoProvider::SEEDANCE))->toThrow(InvalidArgumentException::class);
});
