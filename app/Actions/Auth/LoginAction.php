<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\LoginData;
use App\Enums\AuthGuard;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

readonly class LoginAction
{
    public function __construct(
        #[Auth(AuthGuard::WEB->value)] private StatefulGuard $guard,
        private Session $session,
        private RateLimiter $limiter,
        private Dispatcher $events,
        private Request $request,
    ) {}

    public function run(LoginData $data): void
    {
        $key = Str::transliterate(Str::lower($data->email) . '|' . $this->request->ip());

        if ($this->limiter->tooManyAttempts($key, 5)) {
            $this->events->dispatch(new Lockout($this->request));
            $seconds = $this->limiter->availableIn($key);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        if (! $this->guard->attempt(['email' => $data->email, 'password' => $data->password], $data->remember)) {
            $this->limiter->hit($key);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        $this->limiter->clear($key);
        $this->session->regenerate();
    }
}
