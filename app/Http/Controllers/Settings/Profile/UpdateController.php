<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Profile;

use App\Actions\Settings\UpdateProfileAction;
use App\Data\UpdateProfileData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    /**
     * Изменение имени и email
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
     * @bodyParam name string required Имя до 255 символов. Example: Demo User
     * @bodyParam email string required Уникальный email в нижнем регистре до 255 символов. Example: demo@example.com
     * @response 422 scenario="Validation error (JSON)" {"message": "Введите имя.", "errors": {"name": ["Введите имя."]}}
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function __invoke(UpdateProfileData $data, #[CurrentUser] User $user, UpdateProfileAction $action): RedirectResponse
    {
        if ($action->run($user, $data)) {
            return $this->withStatus(to_route('profile.edit'), 'verification-link-sent');
        }

        return to_route('profile.edit');
    }
}
