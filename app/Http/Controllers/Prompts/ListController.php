<?php

declare(strict_types=1);

namespace App\Http\Controllers\Prompts;

use App\Actions\Prompts\GetPromptsListAction;
use App\Data\GetPromptsListData;
use App\Enums\SortDirection;
use App\Http\Resources\PromptResource;
use Inertia\Response;

class ListController
{
    /**
     * Список идей
     *
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * HTML при обычной навигации; JSON ниже при корректной версии Inertia.
     * Все проекты доступны любому вошедшему пользователю; индивидуального tenant scope нет.
     *
     * @group Кабинет
     * @authenticated
     * @header Accept application/json
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @responseFile 200 docs/responses/prompts.json
     * @response 403 {"message":"Your email address is not verified."}
     * @response 401 {"message":"Unauthenticated."}
     * @queryParam perPage integer Размер страницы, от 1 до 100, по умолчанию 10. Example: 10
     * @queryParam sort string Порядок created_at: asc или desc, по умолчанию desc. Example: desc
     * @queryParam page integer Номер страницы. Example: 1
     * @response 422 {"message":"The selected sort is invalid.","errors":{"sort":["The selected sort is invalid."]}}
     * @responseField component string component.
     * @responseField props object props.
     * @responseField props.errors object props.errors.
     * @responseField props.name string props.name.
     * @responseField props.quote object props.quote.
     * @responseField props.quote.message string props.quote.message.
     * @responseField props.quote.author string props.quote.author.
     * @responseField props.auth object props.auth.
     * @responseField props.auth.user object props.auth.user.
     * @responseField props.auth.user.name string props.auth.user.name.
     * @responseField props.auth.user.email string props.auth.user.email.
     * @responseField props.auth.user.email_verified_at string props.auth.user.email_verified_at.
     * @responseField props.auth.user.updated_at string props.auth.user.updated_at.
     * @responseField props.auth.user.created_at string props.auth.user.created_at.
     * @responseField props.auth.user.id integer props.auth.user.id.
     * @responseField props.ziggy object props.ziggy.
     * @responseField props.ziggy.url string props.ziggy.url.
     * @responseField props.ziggy.port integer props.ziggy.port.
     * @responseField props.ziggy.defaults string[] props.ziggy.defaults.
     * @responseField props.ziggy.routes object props.ziggy.routes.
     * @responseField props.ziggy.location string props.ziggy.location.
     * @responseField props.appearanceValues object Значения оформления из enum.
     * @responseField props.appearanceValues.LIGHT string Светлое оформление.
     * @responseField props.appearanceValues.DARK string Тёмное оформление.
     * @responseField props.appearanceValues.SYSTEM string Системное оформление.
     * @responseField props.sidebarOpen boolean props.sidebarOpen.
     * @responseField props.prompts object props.prompts.
     * @responseField props.prompts.data object[] props.prompts.data.
     * @responseField props.prompts.data[].id integer props.prompts.data[].id.
     * @responseField props.prompts.data[].title string props.prompts.data[].title.
     * @responseField props.prompts.data[].url string props.prompts.data[].url.
     * @responseField props.prompts.data[].project string props.prompts.data[].project.
     * @responseField props.prompts.data[].generated boolean props.prompts.data[].generated.
     * @responseField props.prompts.data[].stage string Этап по сохранённым результатам: image, starting_media, media, video, audio, assembly, ready.
     * @responseField props.prompts.data[].stage_label string Человекочитаемая подпись этапа.
     * @responseField props.prompts.data[].created_at string props.prompts.data[].created_at.
     * @responseField props.prompts.links object props.prompts.links.
     * @responseField props.prompts.links.first string props.prompts.links.first.
     * @responseField props.prompts.links.last string props.prompts.links.last.
     * @responseField props.prompts.links.prev string props.prompts.links.prev. Значение может быть null.
     * @responseField props.prompts.links.next string props.prompts.links.next. Значение может быть null.
     * @responseField props.prompts.meta object props.prompts.meta.
     * @responseField props.prompts.meta.current_page integer props.prompts.meta.current_page.
     * @responseField props.prompts.meta.from integer props.prompts.meta.from.
     * @responseField props.prompts.meta.last_page integer props.prompts.meta.last_page.
     * @responseField props.prompts.meta.links object[] props.prompts.meta.links.
     * @responseField props.prompts.meta.links[].url string props.prompts.meta.links[].url. Значение может быть null.
     * @responseField props.prompts.meta.links[].label string props.prompts.meta.links[].label.
     * @responseField props.prompts.meta.links[].page string props.prompts.meta.links[].page. Значение может быть null.
     * @responseField props.prompts.meta.links[].active boolean props.prompts.meta.links[].active.
     * @responseField props.prompts.meta.path string props.prompts.meta.path.
     * @responseField props.prompts.meta.per_page integer props.prompts.meta.per_page.
     * @responseField props.prompts.meta.to integer props.prompts.meta.to.
     * @responseField props.prompts.meta.total integer props.prompts.meta.total.
     * @responseField props.sortDirections object Порядки сортировки и подписи.
     * @responseField props.sortDirections.asc string Сначала старые.
     * @responseField props.sortDirections.desc string Сначала новые.
     * @responseField props.defaultSort string Порядок сортировки по умолчанию.
     * @responseField props.filters string[] props.filters.
     * @responseField url string url.
     * @responseField version string version.
     * @responseField sharedProps string[] sharedProps.
     */
    public function __invoke(GetPromptsListData $data, GetPromptsListAction $getPromptsList): Response
    {
        $prompts = $getPromptsList->run(
            $data,
        );

        return inertia('Prompts', [
            'prompts' => PromptResource::collection($prompts),
            'sortDirections' => SortDirection::labels(),
            'defaultSort' => SortDirection::DESC->value,
            'filters' => $data->only('sort')->toArray(),
        ]);
    }
}
