<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Data\Projects\ProjectCoverData;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SaveProjectCover
{
    public function run(Project $project, ProjectCoverData $data): void
    {
        $current = $project->coverImage();

        if ($current?->id !== $data->base_id) {
            throw ValidationException::withMessages(['file' => 'Обложка изменилась. Обновите страницу.']);
        }

        if (! $data->file instanceof UploadedFile && ! $current instanceof Media) {
            throw ValidationException::withMessages(['file' => 'Выберите изображение.']);
        }

        $focus = ['focus_x' => $data->x, 'focus_y' => $data->y];

        if (! $data->file instanceof UploadedFile && $current->collection_name === Project::COVER) {
            $current->setCustomProperty('focus_x', $data->x)->setCustomProperty('focus_y', $data->y)->save();

            return;
        }

        $image = $data->file ?? $current->getPath();
        $project->addMedia($image)->preservingOriginal()->withCustomProperties($focus)->toMediaCollection(Project::COVER);

        if ($current?->collection_name === Project::COVER) {
            DB::afterCommit(static fn() => $current->delete());
        }
    }
}
