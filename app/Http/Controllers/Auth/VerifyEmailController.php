<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Подтверждение email по ссылке
     * Сессионная авторизация. Для browser-запроса без сессии — redirect на login; JSON — 401.
     * Redirect после операции: HTTP 302, Location указывает кабинет, login или предыдущую страницу согласно сценарию.
     *
     * @group Проверка email
     * @authenticated
     * @header Accept application/json
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @urlParam id integer required ID текущего пользователя. Example: 1
     * @urlParam hash string required SHA1 email. Example: example-hash
     * @queryParam expires integer required Время истечения подписи. Example: 1794650000
     * @queryParam signature string required Подпись URL. Example: example-signature
     * @response 403 {"message":"Invalid signature."}
     * @response 429 {"message":"Too Many Attempts."}
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false) . '?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            /** @var \Illuminate\Contracts\Auth\MustVerifyEmail $user */
            $user = $request->user();
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard', absolute: false) . '?verified=1');
    }
}
