<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\GetClippingHistoryAction;
use App\Http\Resources\ProjectCardResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function __invoke(#[CurrentUser] User $owner, GetClippingHistoryAction $action): Response
    {
        $data = $action->run($owner);

        return inertia('Clipping/History', [
            ...$data,
            'projects' => $data['projects']->map(static fn(Project $project): array => ProjectCardResource::make($project)->resolve()),
        ]);
    }
}
