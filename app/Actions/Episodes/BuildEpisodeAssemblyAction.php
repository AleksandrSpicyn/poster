<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Models\EpisodeAssembly;
use App\Tasks\Episodes\BuildEpisodeAssembly;
use App\Tasks\Episodes\CompleteEpisodeAssembly;
use App\Tasks\Episodes\ExtractContinuationMedia;
use App\Tasks\Episodes\FailEpisodeAssembly;
use App\Tasks\Episodes\StartEpisodeAssembly;
use Illuminate\Support\Facades\Cache;
use Throwable;

readonly class BuildEpisodeAssemblyAction
{
    public function __construct(
        private StartEpisodeAssembly $start,
        private BuildEpisodeAssembly $build,
        private ExtractContinuationMedia $extract,
        private CompleteEpisodeAssembly $complete,
        private FailEpisodeAssembly $fail,
    ) {}

    public function run(EpisodeAssembly $assembly): void
    {
        Cache::lock('episode-build:' . $assembly->id, 3720)->get(function () use ($assembly): void {
            $video = null;
            $active = $assembly;

            try {
                $current = $this->start->run($assembly);

                if (! $current instanceof EpisodeAssembly) {
                    return;
                }

                $active = $current;
                $video = $this->build->run($current);
                $continuation = $this->extract->run($video);
                $this->complete->run($current, $video, $continuation);
            } catch (Throwable $exception) {
                $this->fail->run($active, $video?->workspace);

                throw $exception;
            }
        });
    }
}
