<?php

declare(strict_types=1);

use App\Actions\Episodes\ApproveEpisodeScriptAction;
use App\Actions\Episodes\SaveEpisodeScriptAction;
use App\Data\Episodes\SaveEpisodeScriptData;
use App\Enums\RevisionStatus;
use Illuminate\Validation\ValidationException;

use function Tests\Support\serialOperator;
use function Tests\Support\storyFixture;
use function Tests\Support\storyScriptPayload;

test('утверждает текущую версию без изменения канона', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $user = serialOperator();
    $revision = app(SaveEpisodeScriptAction::class)->run($episode, SaveEpisodeScriptData::validateAndCreate([
        'script' => storyScriptPayload($character), 'lock_version' => 0,
    ]));

    app(ApproveEpisodeScriptAction::class)->run($revision, $user);
    app(ApproveEpisodeScriptAction::class)->run($revision->refresh(), $user);

    expect($revision->refresh()->status)->toBe(RevisionStatus::APPROVED);
    expect($revision->approved_by)->toBe($user->id);
    expect($episode->series?->canonical_assembly_id)->toBeNull();
});

test('не утверждает сценарий после изменения паспорта сериала', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $revision = app(SaveEpisodeScriptAction::class)->run($episode, SaveEpisodeScriptData::validateAndCreate([
        'script' => storyScriptPayload($character), 'lock_version' => 0,
    ]));
    $episode->series?->increment('specification_version');

    expect(fn() => app(ApproveEpisodeScriptAction::class)->run($revision, serialOperator()))->toThrow(ValidationException::class);

    expect($revision->refresh()->status)->toBe(RevisionStatus::DRAFT);
});
