<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Password;

use App\Actions\Settings\UpdatePasswordAction;
use App\Data\UpdatePasswordData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class UpdateController
{
    /**
     * Изменение пароля
     *
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * Redirect после операции. Для Inertia PUT/PATCH/DELETE возвращается 303, для обычного form submit — 302.
     * Web-валидация возвращает redirect назад и session errors; JSON-вариант — 422.
     *
     * @group Настройки
     * @authenticated
     * @header Accept application/json
     * @response 302
     * @response 303
     * @bodyParam current_password string required Текущий пароль. Example: password-example
     * @bodyParam password string required Новый пароль. Example: new-password
     * @bodyParam password_confirmation string required Подтверждение пароля. Example: new-password
     * @response 422 scenario="Validation error (JSON)" {"message": "Введите текущий пароль.", "errors": {"current_password": ["Введите текущий пароль."]}}
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function __invoke(UpdatePasswordData $data, #[CurrentUser] User $user, UpdatePasswordAction $action): RedirectResponse
    {
        $action->run($user, $data);

        return back();
    }
}
