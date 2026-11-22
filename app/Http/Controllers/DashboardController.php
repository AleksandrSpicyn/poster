<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboardAction;
use App\Http\Resources\PostResource;
use App\Http\Resources\PromptResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

class DashboardController
{
    /**
     * Обзор кабинета
     *
     * Общие счётчики и последние результаты генерации и публикации.
     * Показатели и последние записи относятся к проектам подтверждённого пользователя.
     * Обычный запрос возвращает HTML, Inertia-запрос — страницу Dashboard.
     *
     * @group Кабинет
     * @authenticated
     * @header Accept application/json
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @responseFile 200 docs/responses/dashboard.json
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
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
     * @responseField props.recentPrompts.data object[] props.recentPrompts.data.
     * @responseField props.recentPrompts.data[].id integer props.recentPrompts.data[].id.
     * @responseField props.recentPrompts.data[].title string props.recentPrompts.data[].title.
     * @responseField props.recentPrompts.data[].url string props.recentPrompts.data[].url.
     * @responseField props.recentPrompts.data[].project string props.recentPrompts.data[].project.
     * @responseField props.recentPrompts.data[].generated boolean props.recentPrompts.data[].generated.
     * @responseField props.recentPrompts.data[].stage string Этап по сохранённым результатам: image, starting_media, media, video, audio, assembly, ready.
     * @responseField props.recentPrompts.data[].stage_label string Человекочитаемая подпись этапа.
     * @responseField props.recentPrompts.data[].created_at string props.recentPrompts.data[].created_at.
     * @responseField url string url.
     * @responseField version string version.
     * @responseField sharedProps string[] sharedProps.
     * @responseField props.recentPosts.data object[] props.recentPosts.data.
     * @responseField props.recentPosts.data[].id integer props.recentPosts.data[].id.
     * @responseField props.recentPosts.data[].channel string props.recentPosts.data[].channel.
     * @responseField props.recentPosts.data[].url string props.recentPosts.data[].url.
     * @responseField props.recentPosts.data[].created_at string props.recentPosts.data[].created_at.
     * @responseField props.stats object Счётчики всей истории приложения.
     * @responseField props.stats.prompts integer Всего генераций.
     * @responseField props.stats.pending integer Генерации без готового ролика, включая ожидающие продолжения.
     * @responseField props.stats.ready integer Собранные ролики.
     * @responseField props.stats.posts integer Сохранённые публикации.
     * @responseField props.recentPrompts object Последние шесть генераций в формате PromptResource.
     * @responseField props.recentPosts object Последние пять публикаций в формате PostResource.
     */
    public function __invoke(#[CurrentUser] User $owner, GetDashboardAction $action): Response
    {
        $dashboard = $action->run($owner);

        return inertia('Dashboard', [
            'stats' => [
                'prompts' => $dashboard->promptCount,
                'pending' => $dashboard->pendingCount,
                'ready' => $dashboard->readyCount,
                'posts' => $dashboard->postCount,
            ],
            'recentPrompts' => PromptResource::collection($dashboard->recentPrompts),
            'recentPosts' => PostResource::collection($dashboard->recentPosts),
        ]);
    }
}
