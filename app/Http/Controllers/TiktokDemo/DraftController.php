<?php

declare(strict_types=1);

namespace App\Http\Controllers\TiktokDemo;

use App\Actions\TiktokDemo\DraftAction;
use Illuminate\Http\RedirectResponse;

final class DraftController
{
    public function __invoke(DraftAction $action, string $author): RedirectResponse
    {
        $action->run($author);

        return new RedirectResponse(route('tiktok-demo.show', ['author' => $author], absolute: false));
    }
}
