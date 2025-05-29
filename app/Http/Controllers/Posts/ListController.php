<?php

namespace App\Http\Controllers\Posts;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\GetPostsListRequest;
use App\Tasks\Posts\GetPostsList;
use Inertia\Inertia;
use Inertia\Response;

class ListController extends Controller
{
    public function __invoke(GetPostsListRequest $request, GetPostsList $getPostsList): Response
    {
        $posts = $getPostsList->run(
            $request->toDto(),
        );

        return Inertia::render('Posts', [
            'posts' => $posts,
            'filters' => $request->only(['type', 'sort']),
            'types' => ChannelType::labels(),
        ]);
    }
}
