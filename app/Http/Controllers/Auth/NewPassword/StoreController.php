<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\NewPassword;

use App\Actions\Auth\ResetPasswordAction;
use App\Data\ResetPasswordData;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    /**
     * Смена пароля по токену
     *
     * Redirect после операции: HTTP 302, Location указывает кабинет, login или предыдущую страницу согласно сценарию.
     * Web-валидация возвращает redirect назад и session errors; JSON-вариант — 422.
     *
     * @group Пароли
     * @unauthenticated
     * @header Accept application/json
     * @response 302
     * @bodyParam email string required Email владельца токена. Example: demo@example.com
     * @bodyParam token string required Токен восстановления. Example: example-token
     * @bodyParam password string required Новый пароль. Example: new-password
     * @bodyParam password_confirmation string required Подтверждение пароля. Example: new-password
     * @response 422 scenario="Validation error (JSON)" {"message": "The email field is required.", "errors": {"email": ["The email field is required."]}}
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     */
    public function __invoke(ResetPasswordData $data, ResetPasswordAction $action): RedirectResponse
    {
        $action->run($data);

        return $this->withStatus(to_route('login'), __(PasswordBroker::PASSWORD_RESET));
    }
}
