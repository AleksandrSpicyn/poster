<?php

declare(strict_types=1);

namespace App\Http\Controllers\SeriesAssemblies;

use App\Actions\Series\PublishSeriesVideoAction;
use App\Data\Episodes\PublishEpisodeData;
use App\Models\SeriesAssembly;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class PublishController
{
    /**
     * Опубликовать единый выпуск в закреплённых каналах
     *
     * @group Сериалы
     * @authenticated
     * @urlParam assembly integer required ID итогового монтажа. Example: 1
     * @bodyParam channel_ids integer[] required Каналы из плана цикла. Example: [1]
     * @response 302
     * @response 403
     * @response 422
     */
    public function __invoke(SeriesAssembly $assembly, PublishEpisodeData $data, #[CurrentUser] User $user, PublishSeriesVideoAction $action): RedirectResponse
    {
        $action->run($assembly, $data, $user);

        return back();
    }
}
