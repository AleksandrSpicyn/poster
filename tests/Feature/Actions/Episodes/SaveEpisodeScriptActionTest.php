<?php

declare(strict_types=1);

use App\Actions\Episodes\SaveEpisodeScriptAction;
use App\Data\Episodes\SaveEpisodeScriptData;
use App\Enums\RevisionStatus;
use App\Models\CharacterRevision;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

use function Tests\Support\storyFixture;
use function Tests\Support\storyScriptPayload;

test('сохраняет сценарий и закрепляет персонажей без генерации', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    Process::fake();
    Http::fake();
    $script = storyScriptPayload($character);
    data_forget($script, 'scenes.0.shots.0.dialogue');
    $data = SaveEpisodeScriptData::validateAndCreate(['script' => $script, 'lock_version' => 0]);

    $revision = app(SaveEpisodeScriptAction::class)->run($episode, $data);

    expect($revision->status)->toBe(RevisionStatus::DRAFT);
    expect($revision->scenes()->firstOrFail()->shots()->firstOrFail()->action)->toBe('The hero picks up the key.');
    expect($revision->scenes()->firstOrFail()->shots()->firstOrFail()->dialogue)->toBe([]);
    expect($revision->scenes()->firstOrFail()->shots()->firstOrFail()->source_anchor)->toBe(['time_ms' => 0]);
    expect($revision->characterRevisions()->pluck('character_revisions.id')->all())->toBe([$character->id]);
    expect($episode->refresh()->current_revision_id)->toBe($revision->id);
    expect($episode->lock_version)->toBe(1);
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('немой сценарий сохраняет режим в неизменяемом контексте', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $series = $episode->series()->firstOrFail();
    $series->update(['specification' => [...$series->specification, 'audio_mode' => 'silent']]);

    $data = SaveEpisodeScriptData::validateAndCreate(['script' => storyScriptPayload($character), 'lock_version' => 0]);
    Process::fake();

    $revision = app(SaveEpisodeScriptAction::class)->run($episode, $data);
    $series->update(['specification' => [...$series->specification, 'audio_mode' => 'native'], 'specification_version' => 2]);

    expect(data_get($revision->refresh()->context_snapshot, 'specification.audio_mode'))->toBe('silent');
    expect($revision->scenes()->firstOrFail()->shots()->firstOrFail()->dialogue)->toBe([]);
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('не перезаписывает версию при повторном сохранении устаревшей формы', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $data = SaveEpisodeScriptData::validateAndCreate(['script' => storyScriptPayload($character), 'lock_version' => 0]);
    $first = app(SaveEpisodeScriptAction::class)->run($episode, $data);

    expect(fn() => app(SaveEpisodeScriptAction::class)->run($episode, $data))->toThrow(ValidationException::class);

    expect($episode->revisions()->count())->toBe(1);
    expect($episode->refresh()->current_revision_id)->toBe($first->id);
});

test('отклоняет персонажа из другого сериала', function (): void {
    ['episode' => $episode] = storyFixture();
    $foreign = CharacterRevision::factory()->approved()->create();
    $data = SaveEpisodeScriptData::validateAndCreate(['script' => storyScriptPayload($foreign), 'lock_version' => 0]);

    expect(fn() => app(SaveEpisodeScriptAction::class)->run($episode, $data))->toThrow(ValidationException::class);

    expect($episode->revisions()->exists())->toBeFalse();
});
