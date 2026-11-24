<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\AssemblyStatus;
use App\Models\EpisodeAssembly;
use App\Repositories\EpisodeAssemblyRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FailEpisodeAssembly
{
    public function __construct(private readonly EpisodeAssemblyRepository $assemblies) {}

    public function run(EpisodeAssembly $assembly, ?string $workspace): void
    {
        $expectedVersion = $assembly->lock_version;

        DB::transaction(function () use ($assembly, $expectedVersion): void {
            $assembly = $this->assemblies->findLocked($assembly->id);

            if (! $assembly instanceof EpisodeAssembly || $assembly->lock_version !== $expectedVersion
                || in_array($assembly->status, [AssemblyStatus::READY, AssemblyStatus::ACCEPTED], true)) {
                return;
            }

            $this->assemblies->update($assembly, [
                'status' => AssemblyStatus::FAILED,
                'completed_at' => now(),
                'error_message' => 'The episode could not be assembled. Check the selected takes and request a new assembly.',
                'lock_version' => $assembly->lock_version + 1,
            ]);
        });

        if ($workspace !== null) {
            File::deleteDirectory($workspace);
        }
    }
}
