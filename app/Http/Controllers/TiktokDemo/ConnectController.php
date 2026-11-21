<?php

declare(strict_types=1);

namespace App\Http\Controllers\TiktokDemo;

use App\Actions\TiktokDemo\ConnectAction;
use Illuminate\Http\RedirectResponse;

final class ConnectController
{
    public function __invoke(ConnectAction $action, string $author): RedirectResponse
    {
        return redirect()->away($action->run($author));
    }
}
