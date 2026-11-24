<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Repositories\MediaRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class MoveWorkspaceMediaToPrivateDisk
{
    public function __construct(private MediaRepository $media) {}

    public function run(int $id, bool $apply): void
    {
        DB::transaction(function () use ($id, $apply): void {
            $record = $this->media->findLockedOrFail($id);

            if ($record->disk === 'public') {
                $this->move($record, $apply);
            }
        });
    }

    private function move(Media $media, bool $apply): void
    {
        $relative = $media->getPathRelativeToRoot();
        $dir = dirname($relative);

        if (basename($dir) !== (string) $media->id || basename($relative) !== $media->file_name || ! in_array($media->conversions_disk, [
            '',
            'public',
            'local',
        ], true)) {
            throw new RuntimeException("Unexpected disk layout for media {$media->id}.");
        }

        $source = Storage::disk('public')->path($dir);
        $target = Storage::disk('local')->path($dir);
        $marker = '.poster-move-' . $media->uuid;

        if (is_dir($source)) {
            if (! is_file($source . '/' . $media->file_name) || file_exists($target) || is_link($source)) {
                throw new RuntimeException("Missing original or conflicting destination for media {$media->id}.");
            }

            if (! $apply) {
                return;
            }

            File::ensureDirectoryExists(dirname($target));
            $sourceStat = stat($source);
            $targetStat = stat(dirname($target));

            if ($sourceStat === false || $targetStat === false || $sourceStat['dev'] !== $targetStat['dev']) {
                throw new RuntimeException("Media {$media->id} requires a same-filesystem move.");
            }

            if (! touch($source . '/' . $marker) || ! rename($source, $target)) {
                throw new RuntimeException("Could not move media {$media->id}.");
            }
        } elseif (! is_file($target . '/' . $marker) || ! is_file($target . '/' . $media->file_name)) {
            throw new RuntimeException("Cannot verify a previous move for media {$media->id}.");
        }

        if ($apply) {
            $this->media->markPrivate($media);
            DB::afterCommit(static fn(): bool => File::delete($target . '/' . $marker));
        }
    }
}
