<?php

declare(strict_types=1);

use App\Actions\Shots\AbandonShotGenerationAction;
use App\Actions\Shots\RequestShotGenerationAction;
use App\Data\Shots\AbandonShotGenerationData;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\Shot;
use App\Models\User;
use App\Tasks\Shots\UpdateGenerationOperation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Tests\Support\episodeHttpFixture;
use function Tests\Support\serialReferenceMedia;

test('поздний результат не открывает следующую стадию после решения оператора', function (bool $compareAndSet): void {
    ['operation' => $operation, 'prompt' => $prompt, 'actor' => $actor] = abandonShotActionFixture();
    app(AbandonShotGenerationAction::class)->run($operation, new AbandonShotGenerationData('Unable to establish remote outcome.', true), $actor);
    $receipt = $operation->refresh()->result_snapshot;

    $next = app(UpdateGenerationOperation::class)->run(
        $operation,
        GenerationOperationStatus::SUCCEEDED,
        ['result_snapshot' => ['late_image_media_id' => 123]],
        $compareAndSet ? GenerationOperationStatus::SUBMISSION_UNKNOWN : null,
    );

    expect($next)->toBeNull();
    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::ABANDONED);
    expect($operation->result_snapshot)->toBe($receipt);
    expect($prompt->generationOperations()->count())->toBe(1);
    expect($prompt->refresh()->generated)->toBeFalse();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
    Process::assertNothingRan();
})->with(['обычный worker' => false, 'проверка receipt' => true]);

test('новый дубль требует отдельного ручного запроса после отказа от неизвестного исхода', function (): void {
    ['operation' => $operation, 'prompt' => $prompt, 'shot' => $shot, 'actor' => $actor] = abandonShotActionFixture();
    app(AbandonShotGenerationAction::class)->run($operation, new AbandonShotGenerationData('The external outcome remains unknown.', true), $actor);

    expect($shot->prompts()->count())->toBe(1);
    Bus::assertNothingDispatched();

    $same = app(RequestShotGenerationAction::class)->run($shot, $actor);

    expect($same->id)->toBe($prompt->id);
    expect($shot->prompts()->count())->toBe(1);
    Bus::assertNothingDispatched();

    $new = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'manual-new-take', newTake: true);

    expect($new->id)->not->toBe($prompt->id);
    expect($new->take_number)->toBe(2);
    expect($new->generationOperations()->firstOrFail()->status)->toBe(GenerationOperationStatus::PLANNED);
    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::ABANDONED);
    Bus::assertDispatchedTimes(SubmitShotGenerationJob::class, 1);
    Http::assertNothingSent();
    Process::assertNothingRan();
});

test('решение ждёт завершения текущей проверки под общим lock', function (): void {
    ['operation' => $operation, 'actor' => $actor] = abandonShotActionFixture();
    $lock = Cache::lock('shot-operation:' . $operation->id, 150);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn() => app(AbandonShotGenerationAction::class)->run($operation, new AbandonShotGenerationData('Reviewed.', true), $actor))
            ->toThrow(ValidationException::class);

        expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
});

test('отозванный оператор не закрывает неизвестную операцию через Action', function (): void {
    ['operation' => $operation, 'actor' => $actor] = abandonShotActionFixture();
    User::query()->whereKey($actor->id)->update(['email' => 'revoked@example.test']);

    expect(fn() => app(AbandonShotGenerationAction::class)->run($operation, new AbandonShotGenerationData('Reviewed.', true), $actor))
        ->toThrow(AuthorizationException::class);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
    Process::assertNothingRan();
});

/** @return array{operation: GenerationOperation, prompt: Prompt, shot: Shot, actor: User} */
function abandonShotActionFixture(): array
{
    Storage::fake('local');
    $context = episodeHttpFixture();
    serialReferenceMedia($context['revision']->characterRevisions()->firstOrFail());
    config(['services.video.provider' => 'seedance', 'services.seedance.api_key' => 'test']);
    Bus::fake();
    Http::fake();
    Process::fake();
    $prompt = app(RequestShotGenerationAction::class)->run($context['shot'], $context['user'], requestKey: 'unknown-image');
    $operation = $prompt->generationOperations()->where('stage', GenerationOperationStage::IMAGE)->firstOrFail();
    $operation->update(['status' => GenerationOperationStatus::SUBMISSION_UNKNOWN]);
    Bus::fake();

    return ['operation' => $operation, 'prompt' => $prompt, 'shot' => $context['shot'], 'actor' => $context['user']];
}
