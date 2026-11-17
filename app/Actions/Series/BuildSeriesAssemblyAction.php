<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Models\SeriesAssembly;
use App\Tasks\Series\BuildSeriesAssembly;
use App\Tasks\Series\CompleteSeriesAssembly;
use App\Tasks\Series\FailSeriesAssembly;
use App\Tasks\Series\GetSeriesVideo;
use App\Tasks\Series\StartSeriesAssembly;
use Illuminate\Support\Facades\Cache;
use Throwable;

readonly class BuildSeriesAssemblyAction
{
    public function __construct(private GetSeriesVideo $get, private StartSeriesAssembly $start, private BuildSeriesAssembly $build, private CompleteSeriesAssembly $complete, private FailSeriesAssembly $fail) {}

    public function run(int $id): void
    {
        Cache::lock('series-build:' . $id, 3720)->get(function () use ($id): void {
            $assembly = $this->get->run($id);

            try {
                $current = $this->start->run($assembly);

                if (! $current instanceof SeriesAssembly) {
                    return;
                }

                $this->complete->run($current, $this->build->run($this->get->run($id)));
            } catch (Throwable $exception) {
                $this->fail->run($assembly);

                throw $exception;
            }
        });
    }
}
