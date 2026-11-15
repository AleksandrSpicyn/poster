<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Login;

use App\Actions\Auth\LoginAction;
use App\Data\LoginData;
use Illuminate\Http\RedirectResponse;

class StoreController
{
    /**
     * Вход по email и паролю
     *
     * Redirect после операции: HTTP 302, Location указывает кабинет, login или предыдущую страницу согласно сценарию.
     * Web-валидация возвращает redirect назад и session errors; JSON-вариант — 422.
     * После пяти неудачных попыток email/IP блокируется на минуту; ошибка email остаётся 422.
     *
     * @group Авторизация
     * @unauthenticated
     * @header Accept application/json
     * @response 302
     * @bodyParam email string required Email пользователя. Example: demo@example.com
     * @bodyParam password string required Пароль. Example: password-example
     * @bodyParam remember boolean Долгая сессия. Example: false
     * @response 422 scenario="Validation error (JSON)" {"message": "The email field is required.", "errors": {"email": ["The email field is required."]}}
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     */
    public function __invoke(LoginData $data, LoginAction $action): RedirectResponse
    {
        $action->run($data);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
