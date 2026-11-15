<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Data\ProjectData;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Inertia\Response;

class EditController
{
    /**
     * Форма редактирования проекта
     *
     * Требуются сессия и подтверждённый email. Credentials каналов не возвращаются.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @response 200 {"component":"Projects/Form","props":{"project":{"id":1,"name":"Wildlife","prompt":"Original short stories about animals"},"limits":{"name":255,"prompt":10000}}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project): Response
    {
        return inertia('Projects/Form', [
            'project' => ProjectResource::make($project)->resolve(),
            'limits' => ['name' => ProjectData::NAME_MAX_LENGTH, 'prompt' => ProjectData::PROMPT_MAX_LENGTH],
        ]);
    }
}
