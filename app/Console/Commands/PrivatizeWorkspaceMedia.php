<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\MediaRepository;
use App\Tasks\MoveWorkspaceMediaToPrivateDisk;
use Illuminate\Console\Command;

class PrivatizeWorkspaceMedia extends Command
{
    protected $signature = 'workspace:private-media {--apply : Move files and update the disk on each media record}';

    protected $description = 'Check and move historical workspace media to private local storage';

    public function handle(MediaRepository $media, MoveWorkspaceMediaToPrivateDisk $move): int
    {
        $count = 0;

        foreach ($media->publicWorkspaceMedia() as $record) {
            $move->run($record->id, (bool) $this->option('apply'));
            $count++;
        }

        $this->info("Historical public media checked: {$count}");

        return self::SUCCESS;
    }
}
