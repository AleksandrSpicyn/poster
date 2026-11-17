<?php

declare(strict_types=1);

use App\Actions\Series\BuildSeriesAssemblyAction;
use App\Enums\AssemblyStatus;
use App\Models\SeriesAssembly;
use App\Tasks\Series\SaveSeriesAssembly;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Tests\Support\singleVideoFixture;

test('собирает все принятые части в один файл настоящим ffmpeg', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);

    app(BuildSeriesAssemblyAction::class)->run($assembly->id);

    expect($assembly->refresh()->status)->toBe(AssemblyStatus::READY);
    expect($assembly->duration_ms)->toBeGreaterThanOrEqual(2900);
    expect($assembly->duration_ms)->toBeLessThanOrEqual(3100);
    expect($assembly->getFirstMedia(SeriesAssembly::VIDEO)?->getPath())->toBeFile();
    expect($assembly->publications()->count())->toBe(0);
    expect($context['series']->refresh()->continuity_version)->toBe($context['series']->continuity_version);
    expect(app(SaveSeriesAssembly::class)->run($context['series'])->id)->toBe($assembly->id);
});

test('отклоняет неполный цикл и изменённый файл принятой части', function (bool $missing): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $episode = $context['series']->episodes()->firstOrFail();

    if ($missing) {
        $episode->update(['accepted_assembly_id' => null]);
    } else {
        file_put_contents($episode->acceptedAssembly()->firstOrFail()->getFirstMediaPath('episode-video'), 'changed');
    }

    expect(fn() => app(SaveSeriesAssembly::class)->run($context['series']))->toThrow(ValidationException::class);
    expect(SeriesAssembly::query()->count())->toBe(0);
})->with([true, false]);
