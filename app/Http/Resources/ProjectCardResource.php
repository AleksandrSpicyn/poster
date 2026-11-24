<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Project
 * @property-read Project $resource
 */
final class ProjectCardResource extends JsonResource
{
    /** @return array{id: int, name: string, cover: array{id: int, url: string, source: string, x: int, y: int}|null} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cover' => self::cover($this->resource),
        ];
    }

    /** @return array{id: int, url: string, source: string, x: int, y: int}|null */
    public static function cover(Project $project): ?array
    {
        $media = $project->coverImage();

        if (! $media instanceof Media) {
            return null;
        }

        $x = $media->getCustomProperty('focus_x');
        $y = $media->getCustomProperty('focus_y');

        return [
            'id' => $media->id,
            'url' => route('projects.cover.show', ['project' => $project->id, 'v' => $media->id], absolute: false),
            'source' => $media->collection_name === Project::COVER ? 'upload' : 'frame',
            'x' => is_int($x) ? $x : 50,
            'y' => is_int($y) ? $y : 50,
        ];
    }
}
