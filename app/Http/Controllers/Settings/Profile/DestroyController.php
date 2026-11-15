<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Profile;

use App\Actions\Settings\DeleteProfileAction;
use App\Data\DeleteProfileData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class DestroyController
{
    /**
     * Удаление своего аккаунта
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
     * @bodyParam password string required Текущий пароль. Example: password-example
     * @response 422 scenario="Validation error (JSON)" {"message": "The password field is required.", "errors": {"password": ["The password field is required."]}}
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function __invoke(DeleteProfileData $data, #[CurrentUser] User $user, DeleteProfileAction $action): RedirectResponse
    {
        $action->run($user);

        return redirect('/');
    }
}
