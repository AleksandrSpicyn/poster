<?php

declare(strict_types=1);

namespace App\Http\Controllers\SeriesAssemblies;

use App\Actions\Series\RequestSeriesAssemblyAction;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class StoreController
{
    /**
     * Собрать итоговый ролик из всех принятых частей
     *
     * @group Сериалы
     * @authenticated
     * @urlParam series integer required ID конечного цикла. Example: 1
     * @response 302
     * @response 403
     * @response 422
     */
    public function __invoke(Series $series, #[CurrentUser] User $user, RequestSeriesAssemblyAction $action): RedirectResponse
    {
        return to_route('series_assemblies.show', $action->run($series, $user));
    }
}
