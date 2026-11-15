<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\GetProjectAction;
use App\Data\GetPromptsListData;
use App\Enums\SortDirection;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\PromptResource;
use App\Models\Project;
use Inertia\Response;

class ShowController
{
    /**
     * Проект, каналы и история генераций
     *
     * Требуются сессия и подтверждённый email. Только история выбранного проекта.
     * Каналы содержат id, name, type, type_label, posts_count без credentials.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @urlParam project integer required ID проекта. Example: 1
     * @queryParam sort string Порядок генераций: asc или desc, по умолчанию desc. Example: desc
     * @queryParam perPage integer Размер страницы истории от 1 до 100, по умолчанию 10. Example: 10
     * @queryParam page integer Номер страницы истории от 1. Example: 1
     * @response 200 {"component":"Projects/Show","props":{"project":{"id":1,"name":"Wildlife","prompt":"Original short stories about animals","prompts_count":0,"ready_prompts_count":0,"channels_count":0,"can_delete":true,"channels":[]},"prompts":{"data":[]}}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422 {"message":"The selected sort is invalid.","errors":{"sort":["The selected sort is invalid."]}}
     */
    public function __invoke(Project $project, GetPromptsListData $filters, GetProjectAction $action): Response
    {
        $data = $action->run($project, $filters);

        return inertia('Projects/Show', [
            'project' => ProjectResource::make($data['project'])->resolve(),
            'prompts' => PromptResource::collection($data['prompts']),
            'sortDirections' => SortDirection::labels(),
            'filters' => ['sort' => ($filters->sort instanceof SortDirection ? $filters->sort : SortDirection::DESC)->value],
        ]);
    }
}
