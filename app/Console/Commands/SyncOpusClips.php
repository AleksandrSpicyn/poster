<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\OpusClip\SyncOpusClipsAction;
use App\Classes\OpusClip\OpusClipClient;
use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use RuntimeException;

class SyncOpusClips extends Command
{
    protected $signature = 'opus:sync {--source= : Import one OpusClip project ID} {--game= : Game for the selected source} {--preview : Read without writing to Poster}';

    protected $description = 'Import OpusClip projects and their clips, creating a Poster project for each new source';

    public function handle(OpusClipClient $client, SyncOpusClipsAction $action): int
    {
        $id = $this->option('source');
        $game = $this->option('game');

        if (($game !== null && ($id === null || trim($game) === '' || mb_strlen($game) > 255))
            || ($id !== null && ! preg_match('/^[\w-]{1,128}$/D', $id))) {
            $this->error('Use --source=PROJECT_ID with an optional --game=NAME (1–255 characters).');

            return self::INVALID;
        }

        $result = self::SUCCESS;

        try {
            $sources = $id === null ? $client->sources() : [new OpusSourceData($id, game: $game)];

            foreach ($sources as $source) {
                try {
                    $items = $action->run($source, (bool) $this->option('preview'));
                    $linked = $items->filter(fn(ImportedClipData $clip): bool => collect($clip->publications)->contains(fn(array $post): bool => $post['url'] !== null))->count();
                    $this->info(sprintf('%s: %s %d clips, %d with public links.', $source->id, $this->option('preview') ? 'Found' : 'Synced', $items->count(), $linked));
                } catch (QueryException) {
                    $this->error('Could not save import. Check database connection and migrations.');

                    return self::FAILURE;
                } catch (RuntimeException $error) {
                    $this->error($source->id . ': ' . $error->getMessage());
                    $result = self::FAILURE;
                }
            }
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        return $result;
    }
}
