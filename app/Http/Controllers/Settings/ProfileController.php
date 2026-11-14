<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Настройки профиля
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * HTML при обычной навигации; JSON ниже при корректной версии Inertia.
     *
     * @group Настройки
     * @authenticated
     * @header Accept application/json
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @responseFile 200 docs/responses/profile.json
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
     * @responseField props.mustVerifyEmail boolean props.mustVerifyEmail.
     * @responseField props.status string props.status. Значение может быть null.
     * @responseField url string url.
     * @responseField version string version.
     * @responseField sharedProps string[] sharedProps.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Изменение имени и email
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * Redirect после операции. Для Inertia PUT/PATCH/DELETE возвращается 303, для обычного form submit — 302.
     * Web-валидация возвращает redirect назад и session errors; JSON-вариант — 422.
     *
     * @group Настройки
     * @authenticated
     * @header Accept application/json
     * @response 302
     * @response 303
     * @bodyParam name string required Имя до 255 символов. Example: Demo User
     * @bodyParam email string required Уникальный email в нижнем регистре до 255 символов. Example: demo@example.com
     * @response 422 scenario="Validation error (JSON)" {"message": "The name field is required.", "errors": {"name": ["The name field is required."]}}
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Удаление своего аккаунта
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * Redirect после операции. Для Inertia PUT/PATCH/DELETE возвращается 303, для обычного form submit — 302.
     * Web-валидация возвращает redirect назад и session errors; JSON-вариант — 422.
     *
     * @group Настройки
     * @authenticated
     * @header Accept application/json
     * @response 302
     * @response 303
     * @bodyParam password string required Текущий пароль. Example: password-example
     * @response 422 scenario="Validation error (JSON)" {"message": "The password field is required.", "errors": {"password": ["The password field is required."]}}
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
