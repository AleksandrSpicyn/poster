<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Data\Episodes\BuiltEpisodeVideoData;
use App\Enums\AssemblyStatus;
use App\Models\SeriesAssembly;
use App\Repositories\SeriesAssemblyRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

readonly class CompleteSeriesAssembly
{
    public function __construct(private SeriesAssemblyRepository $assemblies) {}

    public function run(SeriesAssembly $assembly, BuiltEpisodeVideoData $video): void
    {
        $media = null;

        try {
            DB::transaction(function () use ($assembly, $video, &$media): void {
                $current = $this->assemblies->findLockedOrFail($assembly->id);

                if ($current->status !== AssemblyStatus::BUILDING || $current->lock_version !== $assembly->lock_version) {
                    throw new RuntimeException('The final assembly changed during rendering.');
                }

                $media = $current->addMedia($video->path)->withCustomProperties(['checksum' => hash_file('sha256', $video->path)])
                    ->toMediaCollection(SeriesAssembly::VIDEO);
                $this->assemblies->update($current, [
                    'status' => AssemblyStatus::READY,
                    'duration_ms' => $video->durationMs,
                    'completed_at' => now(),
                    'lock_version' => $current->lock_version + 1,
                ]);
            });
        } catch (Throwable $exception) {
            $media?->delete();

            throw $exception;
        } finally {
            File::deleteDirectory($video->workspace);
        }
    }
}
