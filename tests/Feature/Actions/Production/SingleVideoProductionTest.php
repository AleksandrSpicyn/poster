<?php

declare(strict_types=1);

use App\Actions\Production\AdvanceProductionAction;
use App\Actions\Production\ResumeProductionAction;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Jobs\AdvanceProductionJob;
use App\Models\PublicationRequest;
use App\Tasks\Production\CreateProductionRun;
use App\Tasks\Production\StopProductionRun;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\StructuredAnonymousAgent;

use function Tests\Support\singleVideoFixture;

test('последняя часть переходит к общему монтажу без публикации эпизода', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $last = $context['series']->episodes()->orderByDesc('number')->firstOrFail();
    $context['run']->update(['stage' => ProductionStage::STORY, 'episode_assembly_id' => $last->accepted_assembly_id]);
    Process::fake();

    app(AdvanceProductionAction::class)->run($context['run']->id);

    expect($context['run']->refresh()->stage)->toBe(ProductionStage::FINAL_MONTAGE);
    expect(PublicationRequest::query()->count())->toBe(0);
    Http::assertNothingSent();
    Process::assertNothingRan();
});

test('промежуточная часть очищает прошлый результат перед продолжением', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $first = $context['series']->episodes()->orderBy('number')->firstOrFail();
    $context['run']->update(['stage' => ProductionStage::STORY, 'episode_id' => $first->id, 'episode_assembly_id' => $first->accepted_assembly_id,
        'outcome_snapshot' => ['confirmed_events' => 'Old part']]);

    app(AdvanceProductionAction::class)->run($context['run']->id);

    expect($context['run']->refresh()->stage)->toBe(ProductionStage::PREPARING);
    expect($context['run']->episode_id)->toBeNull();
    expect($context['run']->episode_assembly_id)->toBeNull();
    expect($context['run']->outcome_snapshot)->toBeNull();
    expect(PublicationRequest::query()->count())->toBe(0);
});

test('остановка блокирует расписание а явное возобновление идемпотентно', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    Bus::fake([AdvanceProductionJob::class]);
    app(StopProductionRun::class)->run($context['run']);

    $scheduled = app(CreateProductionRun::class)->run($context['project'], $context['series']->refresh(), $context['user'], 'next-schedule');
    app(ResumeProductionAction::class)->run($context['run'], $context['user']);
    app(ResumeProductionAction::class)->run($context['run'], $context['user']);

    expect($scheduled)->toBeNull();
    expect($context['run']->refresh()->status)->toBe(ProductionStatus::STOPPED);
    expect($context['series']->productionRuns()->count())->toBe(2);
    expect($context['series']->refresh()->cycle_paused_at)->toBeNull();
    Bus::assertDispatched(AdvanceProductionJob::class);
});

test('генерирует и закрепляет конечный план до производства частей', function (bool $badCount): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $context['series']->update(['cycle_plan' => null]);
    $context['run']->update(['stage' => ProductionStage::PLANNING]);
    $parts = array_map(fn(int $number): array => ['number' => $number, 'objective' => 'Development ' . $number,
        'expected_result' => 'Result ' . $number, 'scene_key' => 'room', 'transition_mode' => $number === 1 ? 'new_scene' : 'continue_scene'], range(1, $badCount ? 2 : 3));
    StructuredAnonymousAgent::fake([['objective' => 'Reach the ending', 'structure' => 'continuous_story', 'parts' => $parts]])->preventStrayPrompts();

    if ($badCount) {
        expect(fn() => app(AdvanceProductionAction::class)->run($context['run']->id))->toThrow(ValidationException::class);
        expect($context['series']->refresh()->cycle_plan)->toBeNull();
    } else {
        app(AdvanceProductionAction::class)->run($context['run']->id);

        expect($context['run']->refresh()->stage)->toBe(ProductionStage::PREPARING);
        expect($context['series']->refresh()->cycle_plan['parts'] ?? [])->toHaveCount(3);
    }

    Http::assertNothingSent();
})->with([false, true]);
