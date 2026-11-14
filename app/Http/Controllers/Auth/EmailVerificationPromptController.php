<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\GetEmailVerificationStatusAction;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class EmailVerificationPromptController
{
    /**
     * Форма проверки email
     *
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * HTML при обычной навигации; JSON ниже при корректной версии Inertia.
     *
     * @group Проверка email
     * @authenticated
     * @header Accept application/json
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @responseFile 200 docs/responses/verification-notice.json
     * @response 401 {"message":"Unauthenticated."}
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
     * @responseField props.auth.user.email_verified_at string props.auth.user.email_verified_at. Значение может быть null.
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
     * @responseField props.status string props.status. Значение может быть null.
     * @responseField url string url.
     * @responseField version string version.
     * @responseField sharedProps string[] sharedProps.
     */
    public function __invoke(Request $request, #[CurrentUser] User $user, GetEmailVerificationStatusAction $action): RedirectResponse|Response
    {
        return $action->run($user)
                    ? redirect()->intended(route('dashboard', absolute: false))
                    : inertia('auth/VerifyEmail', ['status' => $request->session()->get('status')]);
    }
}
