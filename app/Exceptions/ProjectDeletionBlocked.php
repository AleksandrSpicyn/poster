<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Project;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

final class ProjectDeletionBlocked extends RuntimeException implements ShouldntReport
{
    public function __construct(private readonly Project $project)
    {
        parent::__construct('Удаление недоступно, пока у проекта есть связанные данные или активное производство.');
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        Inertia::flash('error', $this->getMessage());

        return to_route('projects.show', $this->project);
    }
}
