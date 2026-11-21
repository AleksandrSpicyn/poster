<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\OpusClip\YoutubeGamingTrends;
use App\Data\OpusClip\GamingTrendReport;
use App\Models\Project;
use App\Repositories\OpusClipRepository;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class FindGamingTrends extends Command
{
    protected $signature = 'opus:trends {game? : Search one game, or refresh the dashboard for all imported games}';

    protected $description = 'Find short gaming videos on YouTube from the last seven days, ranked by public views';

    public function handle(YoutubeGamingTrends $trends, OpusClipRepository $clips): int
    {
        $game = $this->argument('game');
        $project = $clips->project();

        if ($game !== null && (trim($game) === '' || mb_strlen($game) > 255)) {
            $this->error('Enter a game name of up to 255 characters.');

            return self::INVALID;
        }

        if ($game === null && ! $project instanceof Project) {
            $this->error('Import the gaming project with opus:sync first.');

            return self::FAILURE;
        }

        $games = $game !== null ? [trim($game)] : $clips->games($project);
        $result = self::SUCCESS;

        foreach ($games as $name) {
            try {
                $rows = $trends->search($name);

                if ($project instanceof Project) {
                    $clips->saveTrends($project, new GamingTrendReport($name, $rows));
                }

                $this->info($name);
                $this->table(['Title', 'Channel', 'Views', 'Views/day (min. 1 day)', 'URL'], $rows);
            } catch (RequestException $error) {
                $this->error($name . ': YouTube returned HTTP ' . $error->response->status() . '. Check access and quota.');
                $result = self::FAILURE;
            } catch (ConnectionException|RuntimeException) {
                $this->error($name . ': search unavailable. Check the connection and gaming channel authorization or YOUTUBE_DATA_API_KEY.');
                $result = self::FAILURE;
            }
        }

        return $result;
    }
}
