<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\Series\StoreSeriesAssetAction;
use App\Data\Series\SeriesAssetData;
use App\Http\Controllers\Controller;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

class StoreAssetController extends Controller
{
    /**
     * Добавление материала сериала
     *
     * @group Кабинет
     * @subgroup Сериалы
     * @authenticated
     * @response 302
     * @response 422
     */
    public function __invoke(Series $series, SeriesAssetData $data, StoreSeriesAssetAction $action): RedirectResponse
    {
        $action->run($series, $data);

        return back();
    }
}
