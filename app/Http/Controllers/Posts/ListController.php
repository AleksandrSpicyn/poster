<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Actions\Posts\GetPostsListAction;
use App\Enums\ChannelType;
use App\Http\Requests\Posts\GetPostsListRequest;
use App\Http\Resources\PostResource;
use Inertia\Response;

class ListController
{
    /**
     * Список публикаций
     *
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * HTML при обычной навигации; JSON ниже при корректной версии Inertia.
     * Все публикации доступны любому вошедшему пользователю: индивидуального project/tenant scope в текущей схеме нет.
     *
     * @group Кабинет
     * @authenticated
     * @header Accept application/json
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @responseFile 200 docs/responses/posts.json
     * @response 403 {"message":"Your email address is not verified."}
     * @response 401 {"message":"Unauthenticated."}
     * @queryParam type string Канал: All, youtube, tiktok, instagram, vk. Example: youtube
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
     * @responseField props.sidebarOpen boolean props.sidebarOpen.
     * @responseField props.posts object props.posts.
     * @responseField props.posts.data object[] props.posts.data.
     * @responseField props.posts.data[].id integer props.posts.data[].id.
     * @responseField props.posts.data[].channel string props.posts.data[].channel.
     * @responseField props.posts.data[].url string props.posts.data[].url.
     * @responseField props.posts.data[].created_at string props.posts.data[].created_at.
     * @responseField props.posts.links object props.posts.links.
     * @responseField props.posts.links.first string props.posts.links.first.
     * @responseField props.posts.links.last string props.posts.links.last.
     * @responseField props.posts.links.prev string props.posts.links.prev. Значение может быть null.
     * @responseField props.posts.links.next string props.posts.links.next. Значение может быть null.
     * @responseField props.posts.meta object props.posts.meta.
     * @responseField props.posts.meta.current_page integer props.posts.meta.current_page.
     * @responseField props.posts.meta.from integer props.posts.meta.from.
     * @responseField props.posts.meta.last_page integer props.posts.meta.last_page.
     * @responseField props.posts.meta.links object[] props.posts.meta.links.
     * @responseField props.posts.meta.links[].url string props.posts.meta.links[].url. Значение может быть null.
     * @responseField props.posts.meta.links[].label string props.posts.meta.links[].label.
     * @responseField props.posts.meta.links[].page string props.posts.meta.links[].page. Значение может быть null.
     * @responseField props.posts.meta.links[].active boolean props.posts.meta.links[].active.
     * @responseField props.posts.meta.path string props.posts.meta.path.
     * @responseField props.posts.meta.per_page integer props.posts.meta.per_page.
     * @responseField props.posts.meta.to integer props.posts.meta.to.
     * @responseField props.posts.meta.total integer props.posts.meta.total.
     * @responseField props.filters string[] props.filters.
     * @responseField props.types object props.types.
     * @responseField props.types.youtube string props.types.youtube.
     * @responseField props.types.tiktok string props.types.tiktok.
     * @responseField props.types.instagram string props.types.instagram.
     * @responseField props.types.vk string props.types.vk.
     * @responseField url string url.
     * @responseField version string version.
     * @responseField sharedProps string[] sharedProps.
     */
    public function __invoke(GetPostsListRequest $request, GetPostsListAction $getPostsList): Response
    {
        $posts = $getPostsList->run(
            $request->toDto(),
        );

        return inertia('Posts', [
            'posts' => PostResource::collection($posts),
            'filters' => $request->only(['type', 'sort']),
            'types' => ChannelType::labels(),
        ]);
    }
}
