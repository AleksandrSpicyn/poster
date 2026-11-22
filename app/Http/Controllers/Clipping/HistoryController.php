<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\GetClippingConnectionsAction;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Arr;
use Inertia\Response;

class HistoryController
{
    /**
     * История предпросмотров и подтверждённых импортов
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(#[CurrentUser] User $owner, GetClippingConnectionsAction $action): Response
    {
        $data = $action->history($owner);

        return inertia('Clipping/History', [
            ...$data,
            'projects' => $data['projects']->map(static fn(Project $project): array => Arr::only(ProjectResource::make($project)->resolve(), ['id', 'name', 'cover'])),
        ]);
    }
}
