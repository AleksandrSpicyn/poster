<?php

declare(strict_types=1);

use App\Models\EpisodeAssembly;
use App\Models\Scene;
use App\Support\Integrations\Payload;
use App\Tasks\Production\InspectEpisodeOutcome;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('извлекает кадры готового монтажа и сохраняет только наблюдаемые события', function (): void {
    $disk = Storage::fake('local');
    $home = Storage::fake('codex');
    $home->put('auth.json', '{}');
    config(['services.codex.home' => $home->path('')]);
    $assembly = EpisodeAssembly::factory()->ready()->create(['duration_ms' => 1000]);
    $revision = $assembly->episodeRevision()->firstOrFail();
    $revision->update(['objective' => 'The cat builds a bed.']);
    Scene::factory()
        ->for($revision, 'episodeRevision')
        ->create([
            'objective' => 'The cat unfolds the box into a bed.',
            'expected_final_state' => ['description' => 'The cat is asleep inside the box.'],
            'is_closed' => true,
        ]);
    $assembly->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()
        ->withCustomProperties(['checksum' => hash_file('sha256', base_path('tests/Fixtures/shot.mp4'))])
        ->toMediaCollection(EpisodeAssembly::VIDEO);
    Process::preventStrayProcesses();
    Process::fake([
        '*ffmpeg*' => function (PendingProcess $process): ProcessResult {
            $command = $process->command;

            if (! is_array($command)) {
                throw new LogicException('A frame extraction command must be an argument list.');
            }

            File::copy(base_path('tests/Fixtures/portrait.png'), $command[array_key_last($command)]);

            return Process::result();
        },
        '*codex*' => function (PendingProcess $process): ProcessResult {
            if (! is_string($process->input)) {
                throw new LogicException('An inspection request must contain text.');
            }

            $request = json_decode(Str::before(Str::after($process->input, "\n"), "\n"), true, flags: JSON_THROW_ON_ERROR);
            $input = json_decode(Payload::string(data_get($request, 'messages.0.content'), 'content'), true, flags: JSON_THROW_ON_ERROR);
            expect(data_get($input, 'planned_episode_for_assessment_only'))->toBe([
                'objective' => 'The cat builds a bed.',
                'scenes' => [[
                    'objective' => 'The cat unfolds the box into a bed.',
                    'expected_final_state' => ['description' => 'The cat is asleep inside the box.'],
                    'is_closed' => true,
                ]],
            ]);

            File::put(($process->path ?? '') . '/result.json', json_encode([
                'usable' => true, 'confirmed_events' => 'The cat lies on an opened box.',
                'character_state' => 'The cat rests on the cardboard.', 'open_threads' => '',
                'scene_closed' => true, 'review_notes' => 'The final pose is clear.',
            ], JSON_THROW_ON_ERROR));

            return Process::result();
        },
    ]);

    $outcome = app(InspectEpisodeOutcome::class)->run($assembly);

    expect($outcome->confirmed_events)->toBe('The cat lies on an opened box.');
    expect($outcome->scene_closed)->toBeTrue();
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && $process->command[0] === 'ffmpeg', 6);
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    expect($disk->allDirectories('episode-inspection'))->toBe([]);
});

test('изменённый монтаж не отправляется на анализ', function (): void {
    Storage::fake('local');
    Process::fake();
    $assembly = EpisodeAssembly::factory()->ready()->create(['duration_ms' => 1000]);
    $assembly->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()
        ->withCustomProperties(['checksum' => 'changed'])
        ->toMediaCollection(EpisodeAssembly::VIDEO);

    expect(fn() => app(InspectEpisodeOutcome::class)->run($assembly))->toThrow(RuntimeException::class);

    Process::assertNothingRan();
});
