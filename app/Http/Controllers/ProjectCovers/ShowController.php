<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectCovers;

use App\Models\Project;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShowController
{
    /** Получить приватную обложку своего проекта. */
    public function __invoke(Project $project): BinaryFileResponse
    {
        $image = $project->coverImage();
        abort_if(! $image instanceof Media || ! is_file($image->getPath()), 404);

        return response()->file($image->getPath(), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }
}
