<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\OpusWorkspaceRepository;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use RuntimeException;

class ConfigureOpusWorkspace extends Command
{
    protected $signature = 'opus:configure {project : Existing Poster project ID}
        {--youtube-account= : External ID of the attached YouTube channel}
        {--instagram-account= : Username of the attached Instagram channel}
        {--tiktok-account= : Open ID of the optional attached TikTok channel}';

    protected $description = 'Use an existing Poster project and its channels for the OpusClip workspace';

    public function handle(OpusWorkspaceRepository $workspace): int
    {
        $id = filter_var($this->argument('project'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false) {
            $this->error('Enter a positive Poster project ID.');

            return self::INVALID;
        }

        try {
            $workspace->configure($id, $this->option('youtube-account'), $this->option('instagram-account'), $this->option('tiktok-account'));
        } catch (QueryException) {
            $this->error('Could not save the workspace. Check the database connection, migrations and existing account assignments.');

            return self::FAILURE;
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info('OpusClip workspace configured. Connect each channel in Poster to grant analytics access.');

        return self::SUCCESS;
    }
}
