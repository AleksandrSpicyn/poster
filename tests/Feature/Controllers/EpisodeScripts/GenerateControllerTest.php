<?php

declare(strict_types=1);

use App\Enums\ScriptGenerationStatus;
use App\Jobs\GenerateEpisodeScriptJob;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Tests\Support\serialOperator;
use function Tests\Support\storyFixture;

test('выключенный проект не ставит сценарий в очередь', function (): void {
    ['episode' => $episode] = storyFixture();
    $episode->series?->project?->update(['is_active' => false]);
    Bus::fake();
    Process::fake();

    actingAs(serialOperator())->postJson(route('episodes.scripts.generate', ['series' => $episode->series_id, 'episode' => $episode]), [
        'brief' => 'A new adventure.', 'lock_version' => 0, 'request_key' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonValidationErrors('project');

    expect($episode->refresh()->script_request_key)->toBeNull();
    Bus::assertNothingDispatched();
    Process::assertNothingRan();
});

test('задание сценария сохраняется до фонового запуска и не вызывает CLI в HTTP', function (): void {
    $context = storyFixture();
    $episode = $context['episode'];
    Bus::fake();
    Process::fake();
    $key = (string) Str::uuid();

    actingAs(serialOperator())
        ->post(route('episodes.scripts.generate', ['series' => $episode->series_id, 'episode' => $episode]), ['brief' => 'Start a story about the hidden key.', 'lock_version' => 0, 'request_key' => $key])
        ->assertRedirect();

    expect($episode->refresh()->script_generation_status)->toBe(ScriptGenerationStatus::QUEUED);
    expect($episode->script_request_key)->toBe($key);
    Bus::assertDispatched(GenerateEpisodeScriptJob::class);
    Process::assertNothingRan();
});

test('неподтверждённый пользователь не ставит сценарий в очередь', function (): void {
    $context = storyFixture();
    $episode = $context['episode'];
    Bus::fake();

    actingAs(User::factory()->unverified()->create())
        ->post(route('episodes.scripts.generate', ['series' => $episode->series_id, 'episode' => $episode]), ['brief' => 'Next scene', 'lock_version' => 0, 'request_key' => (string) Str::uuid()])
        ->assertRedirect(route('verification.notice'));

    Bus::assertNothingDispatched();
    expect($episode->refresh()->script_request_key)->toBeNull();
});

test('подтверждённый посторонний пользователь не расходует генерацию сценария', function (): void {
    $context = storyFixture();
    $episode = $context['episode'];
    Bus::fake();
    config(['services.admin.email' => 'operator@example.test', 'horizon.allowed_emails' => []]);

    actingAs(User::factory()->create(['email' => 'reader@example.test']))
        ->postJson(route('episodes.scripts.generate', ['series' => $episode->series_id, 'episode' => $episode]), ['brief' => 'Next scene', 'lock_version' => 0, 'request_key' => (string) Str::uuid()])
        ->assertNotFound();

    expect($episode->refresh()->script_request_key)->toBeNull();
    Bus::assertNothingDispatched();
});
