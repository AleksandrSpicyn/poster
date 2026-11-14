<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LogoutAction;
use Illuminate\Http\RedirectResponse;

class LogoutController
{
    /**
     * Выход из сессии
     *
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * Redirect после операции: HTTP 302, Location указывает кабинет, login или предыдущую страницу согласно сценарию.
     *
     * @group Авторизация
     * @authenticated
     * @header Accept application/json
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     */
    public function __invoke(LogoutAction $action): RedirectResponse
    {
        $action->run();

        return redirect('/');
    }
}
