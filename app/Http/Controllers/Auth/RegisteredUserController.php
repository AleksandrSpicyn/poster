<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Форма регистрации
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
     * @responseField props.sidebarOpen boolean props.sidebarOpen.
     * @responseField url string url.
     * @responseField version string version.
     * @responseField sharedProps string[] sharedProps.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Регистрация пользователя
     * Redirect после операции: HTTP 302, Location указывает кабинет, login или предыдущую страницу согласно сценарию.
     * Web-валидация возвращает redirect назад и session errors; JSON-вариант — 422.
     *
     * @group Авторизация
     * @unauthenticated
     * @header Accept application/json
     * @response 302
     * @bodyParam name string required Имя, не более 255 символов. Example: Demo User
     * @bodyParam email string required Уникальный email в нижнем регистре, до 255 символов. Example: demo@example.com
     * @bodyParam password string required Пароль по текущей политике. Example: password-example
     * @bodyParam password_confirmation string required Совпадает с password. Example: password-example
     * @response 422 scenario="Validation error (JSON)" {"message": "The name field is required.", "errors": {"name": ["The name field is required."]}}
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
