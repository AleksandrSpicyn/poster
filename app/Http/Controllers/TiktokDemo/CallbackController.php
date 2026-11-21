<?php

declare(strict_types=1);

namespace App\Http\Controllers\TiktokDemo;

use App\Actions\TiktokDemo\CallbackAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CallbackController
{
    public function __invoke(CallbackAction $action, Request $request): RedirectResponse
    {
        $author = (string) $request->getUser();
        $action->run($author, $request->query('state'), $request->query('code'));

        return new RedirectResponse(route('tiktok-demo.show', ['author' => $author], absolute: false));
    }
}
