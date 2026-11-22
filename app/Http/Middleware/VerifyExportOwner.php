<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Repositories\UserRepository;
use App\Support\WorkspaceOwner;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyExportOwner
{
    public function __construct(private readonly UserRepository $users) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $media = $request->route('media');
        $ownerId = $media instanceof Model ? WorkspaceOwner::id($media) : null;
        abort_unless($ownerId !== null && $request->integer('owner') === $ownerId && $this->users->isVerified($ownerId), 404);

        return $next($request);
    }
}
