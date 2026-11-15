<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\actingAs;
use function Tests\Support\serialOperator;
use function Tests\Support\storyFixture;
use function Tests\Support\storyScriptPayload;

test('HTTP сохраняет полноценные сцены и планы', function (): void {
    $context = storyFixture();
    $episode = $context['episode'];

    actingAs(serialOperator())
        ->post(route('episodes.scripts.store', ['series' => $episode->series_id, 'episode' => $episode]), ['script' => storyScriptPayload($context['character']), 'lock_version' => 0])
        ->assertRedirect();

    $revision = $episode->refresh()->currentRevision;
    expect($revision?->status->value)->toBe('draft');
    expect($revision?->scenes()->first()?->shots()->first()?->action)->toBe('The hero picks up the key.');
});

test('некорректный сценарий не создаёт версию', function (): void {
    $context = storyFixture();
    $episode = $context['episode'];

    actingAs(serialOperator())
        ->postJson(route('episodes.scripts.store', ['series' => $episode->series_id, 'episode' => $episode]), ['script' => ['title' => 'Draft'], 'lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('script.scenes');

    expect($episode->revisions()->count())->toBe(0);
});

test('немой сериал отклоняет реплики до сохранения и генерации', function (): void {
    $context = storyFixture();
    $episode = $context['episode'];
    $series = $episode->series()->firstOrFail();
    $series->update(['specification' => [...$series->specification, 'audio_mode' => 'silent']]);

    $script = storyScriptPayload($context['character']);
    data_set($script, 'scenes.0.shots.0.dialogue', [['character_id' => $context['character']->character_id, 'text' => 'Do not speak.']]);
    Bus::fake();
    Process::fake();

    actingAs(serialOperator())
        ->postJson(route('episodes.scripts.store', ['series' => $series, 'episode' => $episode]), ['script' => $script, 'lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('script.scenes.0.shots.0.dialogue');

    expect($episode->revisions()->exists())->toBeFalse();
    expect($episode->refresh()->current_revision_id)->toBeNull();
    expect($episode->lock_version)->toBe(0);
    Bus::assertNothingDispatched();
    Process::assertNothingRan();
    Http::assertNothingSent();
});
