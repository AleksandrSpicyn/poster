<?php

declare(strict_types=1);

namespace App\Http\Controllers\SeriesAssemblies;

use App\Actions\Series\GetSeriesAssemblyParametersAction;
use App\Http\Resources\SeriesAssemblyParametersResource;
use App\Models\Series;
use Inertia\Response;

class CreateController
{
    /**
     * Параметры сборки полного сериала
     *
     * @group Сериалы
     * @authenticated
     * @urlParam series integer required ID сериала. Example: 1
     * @response 200
     * @response 404
     */
    public function __invoke(Series $series, GetSeriesAssemblyParametersAction $action): Response
    {
        $series = $action->run($series);

        return inertia('SeriesAssemblies/Create', [
            'series' => ['id' => $series->id, 'name' => $series->name, 'project_id' => $series->project_id],
            'parameters' => SeriesAssemblyParametersResource::make($series)->resolve(),
        ]);
    }
}
