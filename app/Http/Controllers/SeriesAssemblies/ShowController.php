<?php

declare(strict_types=1);

namespace App\Http\Controllers\SeriesAssemblies;

use App\Actions\Series\GetSeriesVideoAction;
use App\Http\Resources\ProjectCardResource;
use App\Http\Resources\SeriesAssemblyParametersResource;
use App\Http\Resources\SeriesAssemblyResource;
use App\Models\SeriesAssembly;
use Illuminate\Support\Arr;
use Inertia\Response;

class ShowController
{
    /**
     * Итоговый монтаж цикла
     *
     * @group Сериалы
     * @authenticated
     * @urlParam assembly integer required ID итогового монтажа. Example: 1
     * @response 200
     * @response 403
     * @response 404
     */
    public function __invoke(SeriesAssembly $assembly, GetSeriesVideoAction $action): Response
    {
        $assembly = $action->run($assembly);

        $series = $assembly->series;
        $newer = $series->relationLoaded('latestAssembly') ? $series->latestAssembly : null;

        return inertia('SeriesAssemblies/Show', [
            'assembly' => SeriesAssemblyResource::make($assembly)->resolve(),
            'series' => [
                'id' => $assembly->series_id,
                'name' => $assembly->series->name,
                'project_id' => $assembly->series->project_id,
                'newer_assembly' => $newer !== null && $newer->number > $assembly->number ? ['number' => $newer->number, 'status' => $newer->status->value] : null,
                'final_assembly_id' => $assembly->series->final_assembly_id,
                'release_started_at' => $assembly->series->release_started_at?->toISOString(),
            ],
            'project' => fn(): array => Arr::only(ProjectCardResource::make($series->project)->resolve(), ['name', 'cover']),
            'parameters' => fn(): array => SeriesAssemblyParametersResource::make($assembly->series)->resolve(),
            'release_started' => $assembly->series->release_started_at !== null,
        ]);
    }
}
