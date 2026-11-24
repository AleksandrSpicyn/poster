<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\ConfirmPassword;

use App\Actions\Auth\ConfirmPasswordAction;
use App\Data\ConfirmPasswordData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    /**
     * Подтверждение пароля сессии
     *
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * Redirect после операции: HTTP 302, Location указывает кабинет, login или предыдущую страницу согласно сценарию.
     * Web-валидация возвращает redirect назад и session errors; JSON-вариант — 422.
     *
     * @group Пароли
     * @authenticated
     * @header Accept application/json
     * @response 302
     * @bodyParam password string required Текущий пароль. Example: password-example
     * @response 422 scenario="Validation error (JSON)" {"message": "The password field is required.", "errors": {"password": ["The password field is required."]}}
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function __invoke(ConfirmPasswordData $data, #[CurrentUser] User $user, ConfirmPasswordAction $action): RedirectResponse
    {
        $action->run($user, $data);

        return $this->intendedDashboard();
    }
}
