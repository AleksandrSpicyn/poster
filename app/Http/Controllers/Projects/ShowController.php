<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\GetProjectAction;
use App\Data\GetPromptsListData;
use App\Data\Series\SeriesWorkspaceData;
use App\Enums\SortDirection;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\EpisodeSummaryResource;
use App\Http\Resources\OpusClipResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\PromptResource;
use App\Http\Resources\SeriesResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
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
     * @queryParam active boolean Только клипы с публикациями. Example: 1
     * @queryParam perPage integer Размер страницы истории от 1 до 100, по умолчанию 10. Example: 10
     * @queryParam page integer Номер страницы истории от 1. Example: 1
     * @response 200 {"component":"Projects/Show","props":{"project":{"id":1,"name":"Wildlife","prompt":"Original short stories about animals","prompts_count":0,"ready_prompts_count":0,"channels_count":0,"can_delete":true,"channels":[]},"prompts":{"data":[]}}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422 {"message":"The selected sort is invalid.","errors":{"sort":["The selected sort is invalid."]}}
     */
    public function __invoke(#[CurrentUser] User $owner, Project $project, GetPromptsListData $filters, GetProjectAction $action): Response
    {
        $workspace = fn(): Project => once(fn(): Project => $action->run($project));
        $series = fn(): ?SeriesWorkspaceData => once(fn(): ?SeriesWorkspaceData => $action->series($owner, $workspace(), $filters->page ?? 1));

        return inertia('Projects/Show', [
            'project' => fn(): array => ProjectResource::make($workspace())->resolve(),
            'gamingTrends' => fn(): array => $action->trends($owner, $project),
            'opusClips' => function () use ($action, $owner, $project, $filters): ?AnonymousResourceCollection {
                $clips = $action->clips($owner, $project, $filters);

                return $clips instanceof LengthAwarePaginator ? OpusClipResource::collection($clips) : null;
            },
            'prompts' => fn(): AnonymousResourceCollection => PromptResource::collection($action->prompts($owner, $project, $filters)),
            'series' => fn(): ?array => $series() instanceof SeriesWorkspaceData ? SeriesResource::make($series()->series)->resolve() : null,
            'characters' => fn(): ?AnonymousResourceCollection => $series() instanceof SeriesWorkspaceData ? CharacterResource::collection($series()->characters) : null,
            'episodes' => fn(): array => EpisodeSummaryResource::collection($series()->episodes ?? [])->resolve(),
            'sortDirections' => SortDirection::labels(),
            'filters' => ['active' => $filters->active, 'sort' => ($filters->sort instanceof SortDirection ? $filters->sort : SortDirection::DESC)->value],
        ]);
    }
}
