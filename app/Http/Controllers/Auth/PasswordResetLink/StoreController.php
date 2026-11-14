<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\PasswordResetLink;

use App\Actions\Auth\SendPasswordResetLinkAction;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use Illuminate\Http\RedirectResponse;

class StoreController
{
    /**
     * Запрос ссылки восстановления
     *
     * Redirect после операции: HTTP 302, Location указывает кабинет, login или предыдущую страницу согласно сценарию.
     * Web-валидация возвращает redirect назад и session errors; JSON-вариант — 422.
     *
     * @group Пароли
     * @unauthenticated
     * @header Accept application/json
     * @response 302
     * @bodyParam email string required Email для восстановления. Example: demo@example.com
     * @response 422 scenario="Validation error (JSON)" {"message": "The email field is required.", "errors": {"email": ["The email field is required."]}}
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     */
    public function __invoke(PasswordResetLinkRequest $request, SendPasswordResetLinkAction $action): RedirectResponse
    {
        $action->run($request->string('email')->toString());

        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}
