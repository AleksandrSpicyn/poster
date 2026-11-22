<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Login;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Response;

class CreateController
{
    /**
     * Форма входа
     *
     * HTML при обычной навигации; JSON ниже при корректной версии Inertia.
     *
     * @group Авторизация
     * @unauthenticated
     * @header Accept application/json
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @responseFile 200 docs/responses/login.json
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
     * @responseField props.credentialsError string Сообщение при неверных данных входа.
     * @responseField props.canResetPassword boolean props.canResetPassword.
     * @responseField props.status string props.status. Значение может быть null.
     * @responseField url string url.
     * @responseField version string version.
     * @responseField sharedProps string[] sharedProps.
     */
    public function __invoke(Request $request): Response
    {
        return inertia('auth/Login', [
            'credentialsError' => __('auth.failed'),
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }
}
