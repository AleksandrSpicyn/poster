<?php

declare(strict_types=1);

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Jobs\CheckShotGenerationJob;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\Scene;
use App\Models\Series;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('подтверждение задачи требует входа', function (): void {
    ['params' => $params, 'operation' => $operation] = resolveShotRouteFixture();
    Http::fake();
    Bus::fake();

    post(route('shots.resolve', $params), ['external_id' => 'remote-1'])->assertRedirect(route('login'));

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

test('неподтверждённый пользователь не сверяет платный запуск', function (): void {
    ['params' => $params, 'operation' => $operation] = resolveShotRouteFixture();
    $actor = User::factory()->unverified()->create();
    Http::fake();
    Bus::fake();

    actingAs($actor)->postJson(route('shots.resolve', $params), ['external_id' => 'remote-1'])->assertForbidden();

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

test('подтверждённый ID продолжает опрос без нового запуска', function (): void {
    ['params' => $params, 'operation' => $operation, 'actor' => $actor] = resolveShotRouteFixture();
    Bus::fake();
    Http::fake(['*contents/generations/tasks/remote-1' => Http::response(['status' => 'running'])]);

    actingAs($actor)->post(route('shots.resolve', $params), ['external_id' => 'remote-1', 'note' => 'Verified in console.'])
        ->assertRedirect(route('episodes.show', ['series' => $params['series'], 'episode' => $params['episode']]) . '#shots');

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMITTED);
    expect($operation->external_id)->toBe('remote-1');
    expect(data_get($operation->result_snapshot, 'resolved_by'))->toBe($actor->id);
    Bus::assertDispatched(CheckShotGenerationJob::class, fn(CheckShotGenerationJob $job): bool => $job->operationId === $operation->id);
    Http::assertSentCount(1);
    Http::assertNotSent(fn(Request $request): bool => $request->method() === 'POST');
});

test('невалидный ID не передаётся провайдеру', function (): void {
    ['params' => $params, 'operation' => $operation, 'actor' => $actor] = resolveShotRouteFixture();
    Http::fake();
    Bus::fake();

    actingAs($actor)->postJson(route('shots.resolve', $params), ['external_id' => '../../other-task'])->assertUnprocessable()->assertInvalid(['external_id']);

    expect($operation->refresh()->external_id)->toBeNull();
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

test('операция другого дубля не проходит вложенный scope', function (): void {
    ['params' => $params, 'operation' => $operation, 'actor' => $actor] = resolveShotRouteFixture();
    $other = Prompt::factory()->recycle(workspaceOwner())->create();
    $foreign = $other->generationOperations()->create(['stage' => GenerationOperationStage::VIDEO, 'status' => GenerationOperationStatus::SUBMISSION_UNKNOWN,
        'request_key' => 'foreign', 'idempotency_key' => hash('sha256', 'foreign'), 'request_snapshot' => []]);
    $params['operation'] = $foreign->id;
    Http::fake();
    Bus::fake();

    actingAs($actor)->postJson(route('shots.resolve', $params), ['external_id' => 'remote-1'])->assertNotFound();

    expect($operation->refresh()->external_id)->toBeNull();
    expect($foreign->refresh()->external_id)->toBeNull();
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});

/** @return array{actor: User, operation: GenerationOperation, params: array<string, int>} */
function resolveShotRouteFixture(): array
{
    config(['services.seedance.api_key' => 'test']);
    $actor = serialOperator();
    $series = Series::factory()->recycle(workspaceOwner())->create();
    $episode = Episode::factory()->recycle(workspaceOwner())->for($series)->create();
    $revision = EpisodeRevision::factory()->for($episode)->approved()->create(['context_snapshot' => ['specification_version' => 1]]);
    $episode->update(['current_revision_id' => $revision->id]);
    $scene = Scene::factory()->for($revision, 'episodeRevision')->create();
    $shot = Shot::factory()->for($scene)->create();
    $prompt = Prompt::factory()->recycle(workspaceOwner())->for($shot)->create(['project_id' => $series->project_id, 'take_number' => 1]);
    $operation = $prompt->generationOperations()->create([
        'stage' => GenerationOperationStage::VIDEO, 'status' => GenerationOperationStatus::SUBMISSION_UNKNOWN,
        'request_key' => 'resolve-http', 'idempotency_key' => hash('sha256', 'resolve-http'),
        'request_snapshot' => ['video' => ['provider' => 'seedance', 'model' => 'dreamina-seedance-2-0-mini-260615', 'generate_audio' => true]],
    ]);

    return ['actor' => $actor, 'operation' => $operation, 'params' => [
        'series' => $series->id, 'episode' => $episode->id, 'revision' => $revision->id,
        'scene' => $scene->id, 'shot' => $shot->id, 'prompt' => $prompt->id, 'operation' => $operation->id,
    ]];
}

test('подтверждённый пользователь без операторского доступа получает отказ', function (): void {
    ['params' => $params, 'operation' => $operation] = resolveShotRouteFixture();
    $actor = User::factory()->create();
    Http::fake();
    Bus::fake();

    actingAs($actor)->postJson(route('shots.resolve', $params), ['external_id' => 'remote-1'])->assertNotFound();

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    Http::assertNothingSent();
    Bus::assertNothingDispatched();
});
