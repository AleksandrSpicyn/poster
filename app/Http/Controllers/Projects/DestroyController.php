<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\DeleteProjectAction;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DestroyController
{
    /**
     * Удаление пустого проекта
     *
     * Требуются сессия, подтверждённый email и CSRF. Проект с каналами или
     * генерациями сохраняется целиком: browser получает redirect и flash error,
     * JSON-запрос — HTTP 409. Inertia DELETE redirect имеет HTTP 303.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @urlParam project integer required ID проекта. Example: 1
     * @response 302
     * @response 303
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 409 {"message":"This project has generations or publishing channels and cannot be deleted."}
     */
    public function __invoke(Project $project, Request $request, DeleteProjectAction $action): RedirectResponse|JsonResponse
    {
        if (! $action->run($project)) {
            $message = 'This project has generations or publishing channels and cannot be deleted.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }

            Inertia::flash('error', $message);

            return to_route('projects.show', $project);
        }

        Inertia::flash('success', 'Project deleted.');

        return to_route('projects.index');
    }
}
