<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\NewPassword;

use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Http\Request;
use Inertia\Response;

class CreateController
{
    /**
     * Форма нового пароля
     *
     * HTML при обычной навигации; JSON ниже при корректной версии Inertia.
     *
     * @group Пароли
     * @unauthenticated
     * @header Accept application/json
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @responseFile 200 docs/responses/password-reset.json
     * @urlParam token string required Токен из письма. Example: example-token
     * @queryParam email string Email из письма. Example: demo@example.com
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
     * @responseField props.sidebarOpen boolean props.sidebarOpen.
     * @responseField props.email string props.email.
     * @responseField props.token string props.token.
     * @responseField url string url.
     * @responseField version string version.
     * @responseField sharedProps string[] sharedProps.
     */
    public function __invoke(Request $request, #[RouteParameter('token')] string $token): Response
    {
        return inertia('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $token,
        ]);
    }
}
