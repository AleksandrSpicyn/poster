<?php

declare(strict_types=1);

use App\Events\WorkspaceUpdated;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

test('изменения отправляются после commit без содержимого проекта', function (): void {
    $project = Project::factory()->create();
    Event::fake([WorkspaceUpdated::class]);

    DB::transaction(function () use ($project): void {
        $project->update(['prompt' => 'Private prompt']);
        Event::assertNotDispatched(WorkspaceUpdated::class);
    });

    Event::assertDispatched(WorkspaceUpdated::class, fn(WorkspaceUpdated $event): bool => $event->broadcastOn()[0]->name === 'private-workspace'
        && $event->broadcastWith() === ['changed' => true]);
});

test('откат не отправляет обновление статусов', function (): void {
    $project = Project::factory()->create();
    Event::fake([WorkspaceUpdated::class]);

    try {
        DB::transaction(function () use ($project): void {
            $project->update(['name' => 'Rolled back']);

            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }

    expect($project->refresh()->name)->not->toBe('Rolled back');
    Event::assertNotDispatched(WorkspaceUpdated::class);
});

test('изменения сериала не отправляются в общий канал', function (): void {
    $series = Series::factory()->create();
    Event::fake([WorkspaceUpdated::class]);

    $series->update(['name' => 'New title']);

    Event::assertDispatched(WorkspaceUpdated::class, fn(WorkspaceUpdated $event): bool => $event->broadcastOn()[0]->name === 'private-workspace.series');
});
