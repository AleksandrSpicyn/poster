<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Appearance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Передаёт тему из cookie в корневой Blade-шаблон до загрузки Vue.
 * Неизвестное значение заменяется системной темой.
 */
class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance');
        $theme = is_string($appearance) ? Appearance::tryFrom($appearance) : null;

        View::share('appearance', ($theme ?? Appearance::SYSTEM)->value);

        return $next($request);
    }
}
