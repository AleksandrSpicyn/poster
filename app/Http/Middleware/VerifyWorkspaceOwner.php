<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\WorkspaceOwner;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWorkspaceOwner
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $owner = $request->user();
        abort_unless($owner instanceof User, 403);

        foreach ($request->route()?->parameters() ?? [] as $resource) {
            if ($resource instanceof Model) {
                abort_unless(WorkspaceOwner::id($resource) === $owner->id, 404);
            }
        }

        return $next($request);
    }
}
