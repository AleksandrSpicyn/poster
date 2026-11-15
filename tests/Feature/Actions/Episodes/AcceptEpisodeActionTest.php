<?php

declare(strict_types=1);

use App\Actions\Episodes\AcceptEpisodeAction;
use App\Actions\Episodes\ApproveEpisodeScriptAction;
use App\Actions\Episodes\SaveEpisodeScriptAction;
use App\Data\Episodes\AcceptEpisodeData;
use App\Data\Episodes\SaveEpisodeScriptData;
use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Tests\Support\serialOperator;
use function Tests\Support\storyFixture;
use function Tests\Support\storyScriptPayload;

test('принятие обновляет историю один раз и инвалидирует продолжения', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $user = serialOperator();
    Storage::fake('local');
    $revision = app(SaveEpisodeScriptAction::class)->run($episode, SaveEpisodeScriptData::validateAndCreate([
        'script' => storyScriptPayload($character), 'lock_version' => 0,
    ]));
    app(ApproveEpisodeScriptAction::class)->run($revision, $user);
    $assembly = EpisodeAssembly::factory()->for($revision)->ready()->create(['duration_ms' => 5000]);
    $assembly->addMedia(UploadedFile::fake()->create('episode.mp4', 10, 'video/mp4'))->toMediaCollection(EpisodeAssembly::VIDEO);
    $later = Episode::factory()->create(['series_id' => $episode->series_id, 'number' => 2, 'predecessor_episode_id' => $episode->id]);
    $data = new AcceptEpisodeData(0, 'The key is found.', 'The hero holds the key.', '', false);

    app(AcceptEpisodeAction::class)->run($assembly, $data, $user);
    app(AcceptEpisodeAction::class)->run($assembly->refresh(), $data, $user);

    expect($assembly->refresh()->status)->toBe(AssemblyStatus::ACCEPTED);
    expect($assembly->continuity_snapshot['confirmed_events'])->toBe('The key is found.');
    expect($episode->refresh()->accepted_assembly_id)->toBe($assembly->id);
    expect($episode->series?->continuity_version)->toBe(1);
    expect($later->refresh()->validity)->toBe(EpisodeValidity::REQUIRES_REVIEW);
});

test('не принимает сборку без готового файла', function (): void {
    $assembly = EpisodeAssembly::factory()->ready()->create();

    expect(fn() => app(AcceptEpisodeAction::class)->run(
        $assembly,
        new AcceptEpisodeData(0, 'Facts', 'State', '', true),
        serialOperator(),
    ))->toThrow(ValidationException::class);

    expect($assembly->refresh()->status)->toBe(AssemblyStatus::READY);
});
