<?php

declare(strict_types=1);

namespace App\Http\Controllers\SeriesAssemblies;

use App\Actions\Series\ReviewSeriesVideoAction;
use App\Data\Series\AcceptSeriesVideoData;
use App\Models\SeriesAssembly;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class AcceptController
{
    /**
     * Принять итоговый ролик после проверки всех стыков со звуком
     *
     * @group Сериалы
     * @authenticated
     * @urlParam assembly integer required ID итогового монтажа. Example: 1
     * @bodyParam lock_version integer required Версия монтажа. Example: 0
     * @bodyParam boundaries_reviewed boolean required Все стыки проверены. Example: true
     * @bodyParam review_notes string required Результат просмотра. Example: Стыки проверены
     * @response 302
     * @response 403
     * @response 422
     */
    public function __invoke(SeriesAssembly $assembly, AcceptSeriesVideoData $data, #[CurrentUser] User $user, ReviewSeriesVideoAction $action): RedirectResponse
    {
        $action->run($assembly, $data, $user);

        return to_route('series_assemblies.show', $assembly);
    }
}
