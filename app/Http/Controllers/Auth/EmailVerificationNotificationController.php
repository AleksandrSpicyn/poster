<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendEmailVerificationAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Повторная отправка проверки email
     *
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * Redirect после операции: HTTP 302, Location указывает кабинет, login или предыдущую страницу согласно сценарию.
     *
     * @group Проверка email
     * @authenticated
     * @header Accept application/json
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 429 {"message":"Too Many Attempts."}
     */
    public function __invoke(#[CurrentUser] User $user, SendEmailVerificationAction $action): RedirectResponse
    {
        if ($action->run($user)) {
            return $this->withStatus(back(), 'verification-link-sent');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
