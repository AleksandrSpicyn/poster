<?php

declare(strict_types=1);

namespace App\Http\Controllers\TiktokDemo;

use App\Actions\TiktokDemo\DirectPostAction;
use App\Data\TiktokDemo\DirectPostData;
use Illuminate\Http\RedirectResponse;

final class DirectPostController
{
    public function __invoke(DirectPostAction $action, DirectPostData $data, string $author): RedirectResponse
    {
        $action->run($author, $data);

        return new RedirectResponse(route('tiktok-demo.show', ['author' => $author], absolute: false));
    }
}
