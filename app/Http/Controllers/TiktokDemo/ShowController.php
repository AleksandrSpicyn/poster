<?php

declare(strict_types=1);

namespace App\Http\Controllers\TiktokDemo;

use App\Actions\TiktokDemo\ShowAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ShowController
{
    public function __invoke(ShowAction $action, Request $request): View
    {
        return view('tiktok-demo.show', $action->run($request->attributes->getString('demo_author'), max(0, $request->integer('cursor'))));
    }
}
