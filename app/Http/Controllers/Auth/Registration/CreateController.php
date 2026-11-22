<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Registration;

use Inertia\Response;

class CreateController
{
    /**
     * Уведомление о закрытой регистрации
     *
     * HTML при обычной навигации; JSON ниже при корректной версии Inertia.
     *
     * @group Авторизация
     * @unauthenticated
     * @header Accept application/json
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @responseFile 200 docs/responses/register.json
     * @responseField component string component.
     * @responseField props object props.
     * @responseField props.errors object props.errors.
     * @responseField props.name string props.name.
     * @responseField props.quote object props.quote.
     * @responseField props.quote.message string props.quote.message.
     * @responseField props.quote.author string props.quote.author.
     * @responseField props.auth object props.auth.
     * @responseField props.auth.user string props.auth.user. Значение может быть null.
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
     * @responseField url string url.
     * @responseField version string version.
     * @responseField sharedProps string[] sharedProps.
     */
    public function __invoke(): Response
    {
        return inertia('auth/Register');
    }
}
