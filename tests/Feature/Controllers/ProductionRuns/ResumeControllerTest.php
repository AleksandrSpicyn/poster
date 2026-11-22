<?php

declare(strict_types=1);

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Jobs\AdvanceProductionJob;
use App\Models\ProductionRun;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;

test('возобновляет свой выпуск после проверки результата без нового дубля', function (): void {
    Bus::fake();
    ['episode' => $episode, 'shot' => $shot, 'user' => $owner] = episodeHttpFixture();
    $series = $episode->series()->firstOrFail();
    $series->update(['automation_enabled' => true]);

    $run = ProductionRun::factory()->for($series)->create([
        'project_id' => $series->project_id, 'episode_id' => $episode->id,
        'actor_id' => $owner->id, 'status' => ProductionStatus::NEEDS_ATTENTION, 'stage' => ProductionStage::MEDIA,
    ]);
    $prompt = Prompt::factory()->for($shot)->create(['project_id' => $series->project_id]);
    $operation = $prompt->generationOperations()->create([
        'production_run_id' => $run->id, 'stage' => GenerationOperationStage::VIDEO,
        'status' => GenerationOperationStatus::SUBMISSION_UNKNOWN, 'request_key' => 'pending-shot',
        'idempotency_key' => 'pending-shot', 'request_snapshot' => [],
    ]);
    Bus::fake();

    actingAs(User::factory()->create())
        ->postJson(route('production_runs.resume', $run))
        ->assertNotFound();
    actingAs($owner)
        ->postJson(route('production_runs.resume', $run))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('production');

    expect($run->refresh()->status)->toBe(ProductionStatus::NEEDS_ATTENTION);
    Bus::assertNothingDispatched();

    $operation->update(['status' => GenerationOperationStatus::SUCCEEDED, 'external_id' => 'existing-receipt']);
    $prompt->update(['generated' => true]);

    actingAs($owner)
        ->post(route('production_runs.resume', $run))
        ->assertRedirect();

    expect($run->refresh()->status)->toBe(ProductionStatus::WAITING);
    expect($run->stage)->toBe(ProductionStage::MEDIA);
    expect($prompt->generationOperations()->sole()->id)->toBe($operation->id);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 1);
    Http::assertNothingSent();
});
