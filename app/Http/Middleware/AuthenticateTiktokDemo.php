<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateTiktokDemo
{
    /** @param Closure(Request):Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('tiktok_demo.enabled') && config('session.driver') !== 'cookie', 404);
        $mode = config()->string('tiktok_demo.mode');
        abort_unless(in_array($mode, ['sandbox', 'production'], true), 503);
        $session = $request->session();

        if ($session->get('tiktok_demo.mode') !== $mode) {
            $session->forget('tiktok_demo');
            $session->put('tiktok_demo.mode', $mode);
        }

        $author = $session->remember('tiktok_demo.owner', static fn(): string => Str::random(32));
        $request->attributes->set('demo_author', $author);

        abort_if($request->route('author') !== null && $request->route('author') !== $author, 404);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
