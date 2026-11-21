<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateTiktokDemo
{
    /** @param Closure(Request):Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('tiktok_demo.enabled') && ! app()->isProduction() && config('session.driver') !== 'cookie', 404);
        $author = $request->getUser();
        $hash = config()->array('tiktok_demo.authors')[$author] ?? null;

        if (! is_string($author) || ! is_string($hash) || ! password_verify((string) $request->getPassword(), $hash)) {
            return response('Требуется вход автора', 401, ['WWW-Authenticate' => 'Basic realm="Poster TikTok demo", charset="UTF-8"', 'Cache-Control' => 'no-store']);
        }

        abort_if($request->route('author') !== null && $request->route('author') !== $author, 404);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
