<?php

declare(strict_types=1);

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\episodeHttpFixture;

test('закрывает неизвестный запуск без внешних эффектов и сохраняет первое решение', function (): void {
    ['params' => $params, 'operation' => $operation, 'actor' => $actor] = abandonShotRouteFixture();
    Bus::fake();
    Http::fake();
    Process::fake();

    actingAs($actor)->post(route('shots.abandon', $params), ['note' => 'Verified manually.', 'accept_unknown_outcome' => true])
        ->assertRedirect(route('episodes.show', ['series' => $params['series'], 'episode' => $params['episode']]));
    $original = $operation->refresh()->result_snapshot;
    actingAs($actor)->post(route('shots.abandon', $params), ['note' => 'Repeated submission.', 'accept_unknown_outcome' => true])
        ->assertRedirect();

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::ABANDONED);
    expect($operation->status->isActive())->toBeFalse();
    expect($operation->result_snapshot)->toBe($original);
    expect(data_get($operation->result_snapshot, 'resolved_by'))->toBe($actor->id);
    expect(data_get($operation->result_snapshot, 'resolution_note'))->toBe('Verified manually.');
    expect(data_get($operation->result_snapshot, 'resolved_at'))->toBeString();
    expect(data_get($operation->result_snapshot, 'accept_unknown_outcome'))->toBeTrue();
    expect(data_get($operation->result_snapshot, 'provider_receipt'))->toBe('retained');
    expect($operation->completed_at)->not->toBeNull();
    expect($operation->next_check_at)->toBeNull();
    expect($operation->prompt?->generated)->toBeFalse();
    expect($operation->prompt?->generationOperations()->count())->toBe(1);
    expect(Prompt::query()->count())->toBe(1);
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
    Process::assertNothingRan();
});

test('решение требует причины и явного принятия неизвестного исхода', function (array $payload, string $field): void {
    ['params' => $params, 'operation' => $operation, 'actor' => $actor] = abandonShotRouteFixture();
    Bus::fake();
    Http::fake();
    Process::fake();

    actingAs($actor)->postJson(route('shots.abandon', $params), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    expect(data_get($operation->result_snapshot, 'resolved_by'))->toBeNull();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
    Process::assertNothingRan();
})->with([
    'нет подтверждения' => [['note' => 'Reviewed.'], 'accept_unknown_outcome'],
    'отказ подтверждения' => [['note' => 'Reviewed.', 'accept_unknown_outcome' => false], 'accept_unknown_outcome'],
    'нет причины' => [['accept_unknown_outcome' => true], 'note'],
    'пустая причина' => [['note' => '   ', 'accept_unknown_outcome' => true], 'note'],
    'слишком длинная причина' => [['note' => str_repeat('a', 1001), 'accept_unknown_outcome' => true], 'note'],
]);

test('гость не закрывает неизвестную операцию', function (): void {
    ['params' => $params, 'operation' => $operation] = abandonShotRouteFixture();
    Bus::fake();
    Http::fake();

    post(route('shots.abandon', $params), ['note' => 'Reviewed.', 'accept_unknown_outcome' => true])
        ->assertRedirect(route('login'));

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('без операторского допуска решение запрещено', function (bool $verified): void {
    ['params' => $params, 'operation' => $operation] = abandonShotRouteFixture();
    $reader = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
    Bus::fake();
    Http::fake();

    actingAs($reader)->postJson(route('shots.abandon', $params), ['note' => 'Reviewed.', 'accept_unknown_outcome' => true])
        ->assertForbidden();

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
})->with(['подтверждённый посторонний' => true, 'неподтверждённый пользователь' => false]);

test('операция другого дубля не меняется', function (): void {
    ['params' => $params, 'actor' => $actor] = abandonShotRouteFixture();
    ['operation' => $foreign] = abandonShotRouteFixture();
    $params['operation'] = $foreign->id;
    Bus::fake();
    Http::fake();

    actingAs($actor)->postJson(route('shots.abandon', $params), ['note' => 'Reviewed.', 'accept_unknown_outcome' => true])
        ->assertNotFound();

    expect($foreign->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('известный запуск не получает статус отказа', function (GenerationOperationStatus $status): void {
    ['params' => $params, 'operation' => $operation, 'actor' => $actor] = abandonShotRouteFixture();
    $operation->update(['status' => $status]);
    Bus::fake();
    Http::fake();

    actingAs($actor)->postJson(route('shots.abandon', $params), ['note' => 'Reviewed.', 'accept_unknown_outcome' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('operation');

    expect($operation->refresh()->status)->toBe($status);
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
})->with([GenerationOperationStatus::SUBMITTED, GenerationOperationStatus::SUCCEEDED]);

/** @return array{params: array<string, int>, operation: GenerationOperation, actor: User} */
function abandonShotRouteFixture(): array
{
    $context = episodeHttpFixture();
    $prompt = Prompt::factory()->create(['project_id' => $context['episode']->series?->project_id,
        'shot_id' => $context['shot']->id, 'take_number' => 1, 'generated' => false]);
    $operation = $prompt->generationOperations()->create([
        'stage' => GenerationOperationStage::IMAGE, 'status' => GenerationOperationStatus::SUBMISSION_UNKNOWN,
        'request_key' => (string) Str::uuid(), 'idempotency_key' => Str::random(64),
        'request_snapshot' => [], 'result_snapshot' => ['provider_receipt' => 'retained'], 'next_check_at' => now(),
    ]);

    return ['params' => [...$context['params'], 'prompt' => $prompt->id, 'operation' => $operation->id],
        'operation' => $operation, 'actor' => $context['user']];
}
