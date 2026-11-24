<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ShowBillingWorkspaceAction;
use App\Data\Billing\BillingHistoryData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

final class HistoryController
{
    /**
     * История платежей и секунд
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @queryParam tab string payments или seconds. Example: seconds
     * @queryParam project integer Фильтр журнала секунд по проекту. Example: 1
     * @queryParam page integer Номер страницы. Example: 1
     * @response 200 {"component":"Billing/Workspace"}
     */
    public function __invoke(#[CurrentUser] User $owner, BillingHistoryData $data, ShowBillingWorkspaceAction $action): Response
    {
        return inertia('Billing/Workspace', $action->run($owner->id, $data->tab, $data->page, project: $data->project));
    }
}
