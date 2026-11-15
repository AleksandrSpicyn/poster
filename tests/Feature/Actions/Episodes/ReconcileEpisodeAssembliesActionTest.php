<?php

declare(strict_types=1);

use App\Actions\Episodes\ReconcileEpisodeAssembliesAction;
use App\Enums\AssemblyStatus;
use App\Jobs\BuildEpisodeAssemblyJob;
use App\Models\EpisodeAssembly;
use Illuminate\Support\Facades\Queue;

test('восстанавливает потерянную доставку и прерванную сборку с ограничением объема', function (): void {
    $queued = EpisodeAssembly::factory()->create(['status' => AssemblyStatus::QUEUED, 'updated_at' => now()->subMinutes(2)]);
    $interrupted = EpisodeAssembly::factory()->create(['status' => AssemblyStatus::BUILDING, 'started_at' => now()->subHours(2)]);
    $running = EpisodeAssembly::factory()->create(['status' => AssemblyStatus::BUILDING, 'started_at' => now()]);
    $ready = EpisodeAssembly::factory()->ready()->create();
    Queue::fake([BuildEpisodeAssemblyJob::class]);
    $action = app(ReconcileEpisodeAssembliesAction::class);

    $restored = $action->run(1);
    $restoredAgain = $action->run(100);

    expect($restored)->toBe(1);
    expect($restoredAgain)->toBe(1);
    expect($queued->refresh()->status)->toBe(AssemblyStatus::QUEUED);
    expect($queued->lock_version)->toBe(1);
    expect($interrupted->refresh()->status)->toBe(AssemblyStatus::QUEUED);
    expect($interrupted->started_at)->toBeNull();
    expect($running->refresh()->status)->toBe(AssemblyStatus::BUILDING);
    expect($ready->refresh()->status)->toBe(AssemblyStatus::READY);
    Queue::assertPushed(BuildEpisodeAssemblyJob::class, 2);
});
