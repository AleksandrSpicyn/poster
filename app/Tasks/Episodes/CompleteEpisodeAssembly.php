<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\BuiltEpisodeVideoData;
use App\Data\Episodes\ContinuationMediaData;
use App\Enums\AssemblyStatus;
use App\Models\EpisodeAssembly;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class CompleteEpisodeAssembly
{
    public function run(EpisodeAssembly $assembly, BuiltEpisodeVideoData $video, ContinuationMediaData $continuation): void
    {
        /** @var list<Media> $created */
        $created = [];
        $expectedVersion = $assembly->lock_version;

        try {
            DB::transaction(function () use ($assembly, $video, $continuation, $expectedVersion, &$created): void {
                $assembly = EpisodeAssembly::query()->lockForUpdate()->findOrFail($assembly->id);

                if ($assembly->status !== AssemblyStatus::BUILDING || $assembly->lock_version !== $expectedVersion) {
                    throw new RuntimeException('The assembly is no longer being built.');
                }

                foreach ([
                    [$video->path, EpisodeAssembly::VIDEO],
                    [$continuation->framePath, EpisodeAssembly::CONTINUATION_FRAME],
                    [$continuation->tailPath, EpisodeAssembly::CONTINUATION_TAIL],
                ] as [$path, $collection]) {
                    $checksum = hash_file('sha256', $path);

                    if ($checksum === false) {
                        throw new RuntimeException('An assembled media file is missing.');
                    }

                    $created[] = $assembly->addMedia($path)
                        ->withCustomProperties(['checksum' => $checksum, 'duration_ms' => $video->durationMs,
                            'time_ms' => $continuation->timeMs, 'assembly_id' => $assembly->id])
                        ->toMediaCollection($collection);
                }

                $assembly->update([
                    'status' => AssemblyStatus::READY, 'duration_ms' => $video->durationMs,
                    'completed_at' => now(), 'error_message' => null, 'lock_version' => $assembly->lock_version + 1,
                ]);
            });
        } catch (Throwable $exception) {
            foreach ($created as $media) {
                $media->delete();
            }

            throw $exception;
        } finally {
            File::deleteDirectory($video->workspace);
        }
    }
}
