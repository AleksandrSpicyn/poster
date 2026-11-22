<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\serialOperator;
use function Tests\Support\storyFixture;
use function Tests\Support\storyScriptPayload;

test('HTTP сохраняет полноценные сцены и планы', function (): void {
    $context = storyFixture();
    $episode = $context['episode'];
    $script = storyScriptPayload($context['character']);
    Arr::set($script, 'scenes.0.shots.0.title', 'На развилке');
    Arr::set($script, 'scenes.0.shots.0.plan_type', 'Общий план');

    actingAs(serialOperator())
        ->post(route('episodes.scripts.store', ['series' => $episode->series_id, 'episode' => $episode]), ['script' => $script, 'lock_version' => 0])
        ->assertRedirect();

    $revision = $episode->refresh()->currentRevision;
    expect($revision?->status->value)->toBe('draft');
    $shot = $revision?->scenes()->first()?->shots()->first();
    expect($shot?->action)->toBe('The hero picks up the key.')
        ->and($shot?->title)->toBe('На развилке')
        ->and($shot?->plan_type)->toBe('Общий план')
        ->and($shot?->camera)->toBe(['description' => 'Medium shot']);
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

test('сохраняет несколько сцен и их кадры пакетно в порядке сценария', function (): void {
    $context = storyFixture();
    $episode = $context['episode'];
    $script = storyScriptPayload($context['character']);
    /** @var array{title: string, shots: non-empty-list<array<string, mixed>>} $scene */
    $scene = data_get($script, 'scenes.0');
    $scene['shots'] = array_fill(0, 10, $scene['shots'][0]);
    $script['scenes'] = [$scene, [...$scene, 'title' => 'Second scene']];
    actingAs(serialOperator());

    DB::enableQueryLog();

    try {
        post(route('episodes.scripts.store', ['series' => $episode->series_id, 'episode' => $episode]), ['script' => $script, 'lock_version' => 0])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $writes = collect(DB::getQueryLog())->pluck('query')->filter(fn(mixed $sql): bool => is_string($sql) && preg_match('/^insert into [`"](scenes|shots)[`"]/i', $sql) === 1);
    } finally {
        DB::disableQueryLog();
    }

    $scenes = $episode->revisions()->sole()->scenes()->with('shots')->get();
    expect($writes)->toHaveCount(2)
        ->and($scenes->pluck('title')->all())->toBe([$scene['title'], 'Second scene']);

    foreach ($scenes as $saved) {
        expect($saved->shots->pluck('position')->all())->toBe(range(1, 10));
    }
});
