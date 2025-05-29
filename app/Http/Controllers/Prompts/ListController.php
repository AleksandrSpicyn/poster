<?php

namespace App\Http\Controllers\Prompts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prompts\GetPromptsListRequest;
use App\Http\Resources\PromptResource;
use App\Tasks\Prompts\GetPromptsList;
use Inertia\Inertia;
use Inertia\Response;

class ListController extends Controller
{
    public function __invoke(GetPromptsListRequest $request, GetPromptsList $getPromptsList): Response
    {
        $prompts = $getPromptsList->run(
            $request->toDto(),
        );

        return Inertia::render('Prompts', [
            'prompts' => PromptResource::collection($prompts),
            'filters' => $request->only(['sort']),
        ]);
    }
}
