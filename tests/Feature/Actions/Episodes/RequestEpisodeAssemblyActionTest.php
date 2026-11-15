<?php

declare(strict_types=1);

use App\Actions\Episodes\RequestEpisodeAssemblyAction;
use App\Data\Episodes\AssemblyManifestData;
use App\Data\Episodes\AssemblyShotData;
use App\Enums\AssemblyStatus;
use App\Jobs\BuildEpisodeAssemblyJob;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Tests\Support\assemblyFixture;

require_once dirname(__DIR__, 3) . '/Support/Assemblies.php';

test('фиксирует точный дубль и trim без повторной сборки одинакового manifest', function (): void {
    Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision, 'prompt' => $prompt, 'media' => $media] = assemblyFixture();
    $data = new AssemblyManifestData([new AssemblyShotData($prompt->id, 0, 1000)]);
    $action = app(RequestEpisodeAssemblyAction::class);

    $assembly = $action->run($revision, $data);
    $repeated = $action->run($revision, $data);

    expect($assembly->status)->toBe(AssemblyStatus::QUEUED);
    expect($repeated->id)->toBe($assembly->id);
    expect(data_get($assembly->manifest, 'shots.0.media_id'))->toBe($media->id);
    expect(data_get($assembly->manifest, 'shots.0.trim_end_ms'))->toBe(1000);
    expect($assembly->manifest['duration_ms'])->toBe(1000);
    expect($assembly->refresh()->continuity_snapshot)->toBe([]);
    expect(EpisodeAssembly::query()->count())->toBe(1);
    Queue::assertPushed(BuildEpisodeAssemblyJob::class, 1);
});

test('не принимает дубль другого плана', function (): void {
    Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision] = assemblyFixture();
    $other = Prompt::factory()->create(['generated' => true]);

    expect(fn() => app(RequestEpisodeAssemblyAction::class)->run($revision, new AssemblyManifestData([new AssemblyShotData($other->id)])))
        ->toThrow(ValidationException::class);

    expect(EpisodeAssembly::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('не собирает устаревший сценарий и не теряет файлы дублей', function (): void {
    $disk = Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision, 'prompt' => $prompt, 'media' => $media] = assemblyFixture();
    $series = $revision->episode()->firstOrFail()->series()->firstOrFail();
    $series->increment('specification_version');

    expect(fn() => app(RequestEpisodeAssemblyAction::class)->run($revision, new AssemblyManifestData([new AssemblyShotData($prompt->id, 0, 1000)])))
        ->toThrow(ValidationException::class);

    $disk->assertExists($media->getPathRelativeToRoot());
    Queue::assertNothingPushed();
});

test('отклоняет trim за границами плана', function (): void {
    Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision, 'prompt' => $prompt] = assemblyFixture();

    expect(fn() => app(RequestEpisodeAssemblyAction::class)->run($revision, new AssemblyManifestData([new AssemblyShotData($prompt->id, 4900, 6000)])))
        ->toThrow(ValidationException::class);

    Queue::assertNothingPushed();
});

test('повтор запроса принятой сборки не создает новый монтаж после продвижения истории', function (): void {
    Storage::fake('local');
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    ['revision' => $revision, 'prompt' => $prompt] = assemblyFixture();
    $data = new AssemblyManifestData([new AssemblyShotData($prompt->id, 0, 1000)]);
    $action = app(RequestEpisodeAssemblyAction::class);
    $assembly = $action->run($revision, $data);
    $assembly->update(['status' => AssemblyStatus::ACCEPTED]);

    $episode = $revision->episode()->firstOrFail();
    $episode->update(['accepted_assembly_id' => $assembly->id]);
    $episode->series()->firstOrFail()->increment('continuity_version');

    $repeated = $action->run($revision, $data);

    expect($repeated->id)->toBe($assembly->id);
    expect($repeated->status)->toBe(AssemblyStatus::ACCEPTED);
    expect(EpisodeAssembly::query()->count())->toBe(1);
    Queue::assertPushed(BuildEpisodeAssemblyJob::class, 1);
});
