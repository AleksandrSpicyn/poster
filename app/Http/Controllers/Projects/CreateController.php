<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Data\ProjectData;
use Inertia\Response;

class CreateController
{
    /**
     * Форма создания проекта
     *
     * Требуются сессия и подтверждённый email. Не запускает генерацию или публикацию.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @response 200 {"component":"Projects/Form","props":{"project":null,"limits":{"name":255,"prompt":10000}}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     */
    public function __invoke(): Response
    {
        return inertia('Projects/Form', [
            'project' => null,
            'limits' => ['name' => ProjectData::NAME_MAX_LENGTH, 'prompt' => ProjectData::PROMPT_MAX_LENGTH],
        ]);
    }
}
