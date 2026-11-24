<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ShowBillingWorkspaceAction;
use App\Data\PageData;
use App\Enums\BillingSection;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

final class ShowController
{
    /**
     * Кабинет подписки
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @urlParam section string Раздел кабинета. Example: plans
     * @queryParam page integer Страница истории. Example: 1
     * @response 200 {"component":"Billing/Workspace"}
     */
    public function __invoke(#[CurrentUser] User $owner, PageData $data, ShowBillingWorkspaceAction $action, BillingSection $section = BillingSection::OVERVIEW): Response
    {
        return inertia('Billing/Workspace', $action->run($owner->id, $section, $data->page));
    }
}
