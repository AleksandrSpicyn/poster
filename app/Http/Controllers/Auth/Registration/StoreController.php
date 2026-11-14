<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Registration;

use App\Actions\Auth\RegisterUserAction;
use App\Http\Requests\Auth\RegisterUserRequest;
use Illuminate\Http\RedirectResponse;

class StoreController
{
    /**
     * Регистрация пользователя
     *
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
    public function __invoke(RegisterUserRequest $request, RegisterUserAction $action): RedirectResponse
    {
        $action->run($request->toDto());

        return to_route('dashboard');
    }
}
