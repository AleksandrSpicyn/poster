<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CharacterRevision;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\SeriesAssembly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PrivatizeWorkspaceMedia extends Command
{
    protected $signature = 'workspace:private-media {--apply : Move files and update the disk on each media record}';

    protected $description = 'Check and move historical workspace media to private local storage';

    public function handle(): int
    {
        $types = array_map(static fn(string $class): string => new $class()->getMorphClass(), [
            Prompt::class, EpisodeAssembly::class, SeriesAssembly::class, CharacterRevision::class,
        ]);
        $count = 0;

        foreach (Media::query()->whereIn('model_type', $types)->where('disk', 'public')->lazyById() as $record) {
            DB::transaction(function () use ($record): void {
                $media = Media::query()->lockForUpdate()->findOrFail($record->id);

                if ($media->disk === 'public') {
                    $this->move($media);
                }
            });
            $count++;
        }

        $this->info("Historical public media checked: {$count}");

        return self::SUCCESS;
    }

    private function move(Media $media): void
    {
        $relative = $media->getPathRelativeToRoot();
        $dir = dirname($relative);

        if (basename($dir) !== (string) $media->id || basename($relative) !== $media->file_name || ! in_array($media->conversions_disk, ['', 'public', 'local'], true)) {
            throw new RuntimeException("Unexpected disk layout for media {$media->id}.");
        }

        $source = Storage::disk('public')->path($dir);
        $target = Storage::disk('local')->path($dir);
        $marker = '.poster-move-' . $media->uuid;

        if (is_dir($source)) {
            if (! is_file($source . '/' . $media->file_name) || file_exists($target) || is_link($source)) {
                throw new RuntimeException("Missing original or conflicting destination for media {$media->id}.");
            }

            if (! $this->option('apply')) {
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

        if ($this->option('apply')) {
            $media->disk = 'local';
            $media->conversions_disk = 'local';
            $media->saveQuietly();
            DB::afterCommit(static fn(): bool => File::delete($target . '/' . $marker));
        }
    }
}
