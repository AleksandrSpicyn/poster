<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use LogicException;

/** @param Closure(PendingProcess): ProcessResult $response */
function fakeCodex(Closure $response): void
{
    $home = Storage::fake('codex');
    $home->put('auth.json', '{}');
    config(['services.codex.home' => $home->path('')]);
    Process::preventStrayProcesses();
    Process::fake(['*codex*' => $response]);
}

/** @param Closure(PendingProcess): ProcessResult|null $response */
function fakeCodexImages(?Closure $response = null): void
{
    fakeCodex($response ?? function (PendingProcess $process): ProcessResult {
        $workspace = $process->path ?? throw new LogicException('Codex workspace is missing.');
        File::ensureDirectoryExists($workspace . '/generated_images');
        $imagePath = $workspace . '/generated_images/first-frame.png';
        File::copy(base_path('tests/Fixtures/portrait.png'), $imagePath);
        File::put($workspace . '/result.json', json_encode(['image_path' => $imagePath], JSON_THROW_ON_ERROR));

        return Process::result();
    });
}
