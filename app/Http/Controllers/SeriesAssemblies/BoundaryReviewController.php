<?php

declare(strict_types=1);

namespace App\Http\Controllers\SeriesAssemblies;

use App\Actions\Series\SaveSeriesBoundaryReviewAction;
use App\Data\Series\SaveBoundaryReviewData;
use App\Models\SeriesAssembly;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class BoundaryReviewController
{
    /**
     * Сохранить проверку перехода между эпизодами
     *
     * @group Сериалы
     * @authenticated
     * @urlParam assembly integer required ID сборки. Example: 1
     * @urlParam boundary integer required Номер эпизода перед стыком. Example: 1
     * @bodyParam reviewed boolean required Переход проверен. Example: true
     * @bodyParam notes string Заметка проверяющего. Example: Звук непрерывен
     * @bodyParam lock_version integer required Текущая версия сборки. Example: 2
     * @response 302
     * @response 404
     * @response 422
     */
    public function __invoke(SeriesAssembly $assembly, int $boundary, SaveBoundaryReviewData $data, #[CurrentUser] User $actor, SaveSeriesBoundaryReviewAction $action): RedirectResponse
    {
        $action->run($assembly, $boundary, $data, $actor);

        return to_route('series_assemblies.show', $assembly);
    }
}
