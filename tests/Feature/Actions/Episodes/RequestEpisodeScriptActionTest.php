<?php

declare(strict_types=1);

use App\Actions\Episodes\ReconcileEpisodeScriptsAction;
use App\Actions\Episodes\RequestEpisodeScriptAction;
use App\Data\Episodes\GenerateEpisodeScriptData;
use App\Enums\ScriptGenerationStatus;
use App\Jobs\GenerateEpisodeScriptJob;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

use function Tests\Support\serialOperator;
use function Tests\Support\storyFixture;

test('запрос сценария фиксирует контекст и не выполняет генерацию в HTTP', function (): void {
    ['episode' => $episode] = storyFixture();
    $user = serialOperator();
    Queue::fake([GenerateEpisodeScriptJob::class]);
    Process::fake();
    $data = new GenerateEpisodeScriptData('Find the key.', 0, '7e061dbf-290b-49ea-8ac3-cc69ed236884');

    app(RequestEpisodeScriptAction::class)->run($episode, $data, $user);
    app(RequestEpisodeScriptAction::class)->run($episode, $data, $user);

    expect($episode->refresh()->script_generation_status)->toBe(ScriptGenerationStatus::QUEUED);
    expect(data_get($episode->script_request_payload, 'context.specification_version'))->toBe(1);
    Queue::assertPushed(GenerateEpisodeScriptJob::class, 1);
    Process::assertNothingRan();
});

test('восстановление не повторяет неизвестный исход текстового запроса', function (): void {
    ['episode' => $episode] = storyFixture();
    $episode->update([
        'script_generation_status' => ScriptGenerationStatus::RUNNING,
        'script_started_at' => now()->subMinutes(11),
        'script_request_key' => '7e061dbf-290b-49ea-8ac3-cc69ed236884',
    ]);
    Queue::fake([GenerateEpisodeScriptJob::class]);

    app(ReconcileEpisodeScriptsAction::class)->run();

    expect($episode->refresh()->script_generation_status)->toBe(ScriptGenerationStatus::OUTCOME_UNKNOWN);
    Queue::assertNothingPushed();
});

test('посторонний пользователь не ставит сценарий в очередь', function (): void {
    ['episode' => $episode] = storyFixture();
    $user = User::factory()->create();
    config(['services.admin.email' => 'owner@example.test', 'horizon.allowed_emails' => []]);
    Queue::fake();
    Process::fake();
    $data = new GenerateEpisodeScriptData('Find the key.', 0, '7e061dbf-290b-49ea-8ac3-cc69ed236884');

    expect(fn() => app(RequestEpisodeScriptAction::class)->run($episode, $data, $user))
        ->toThrow(AuthorizationException::class);

    expect($episode->refresh()->script_request_key)->toBeNull();
    expect($episode->revisions()->exists())->toBeFalse();
    Queue::assertNothingPushed();
    Process::assertNothingRan();
});
