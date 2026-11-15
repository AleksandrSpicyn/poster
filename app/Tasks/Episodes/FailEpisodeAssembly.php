<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\AssemblyStatus;
use App\Models\EpisodeAssembly;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FailEpisodeAssembly
{
    public function run(EpisodeAssembly $assembly, ?string $workspace): void
    {
        $expectedVersion = $assembly->lock_version;

        DB::transaction(function () use ($assembly, $expectedVersion): void {
            $assembly = EpisodeAssembly::query()->lockForUpdate()->find($assembly->id);

            if ($assembly === null || $assembly->lock_version !== $expectedVersion
                || in_array($assembly->status, [AssemblyStatus::READY, AssemblyStatus::ACCEPTED], true)) {
                return;
            }

            $assembly->update([
                'status' => AssemblyStatus::FAILED, 'completed_at' => now(),
                'error_message' => 'The episode could not be assembled. Check the selected takes and request a new assembly.',
                'lock_version' => $assembly->lock_version + 1,
            ]);
        });

        if ($workspace !== null) {
            File::deleteDirectory($workspace);
        }
    }
}
