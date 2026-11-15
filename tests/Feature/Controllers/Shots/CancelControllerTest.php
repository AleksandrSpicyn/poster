<?php

declare(strict_types=1);

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\Prompt;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;

test('остановка отменяет запланированную стадию выбранного дубля', function (): void {
    $context = episodeHttpFixture();
    $take = Prompt::factory()->create(['project_id' => $context['episode']->series?->project_id, 'shot_id' => $context['shot']->id, 'take_number' => 1]);
    $operation = $take->generationOperations()->create(['stage' => GenerationOperationStage::IMAGE, 'status' => GenerationOperationStatus::PLANNED, 'request_key' => 'stop-http', 'idempotency_key' => hash('sha256', 'stop-http'), 'request_snapshot' => []]);

    actingAs($context['user'])->post(route('shots.cancel', [...$context['params'], 'prompt' => $take]))->assertRedirect();

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::CANCELLED);
    expect($operation->cancel_requested_at)->not->toBeNull();
});

test('остановка не затрагивает чужой дубль', function (): void {
    $context = episodeHttpFixture();
    $other = Prompt::factory()->create();

    actingAs($context['user'])
        ->postJson(route('shots.cancel', [...$context['params'], 'prompt' => $other]))
        ->assertNotFound();
});
