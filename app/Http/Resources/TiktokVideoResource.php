<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EpisodeAssembly;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\SeriesAssembly;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @mixin Media */
class TiktokVideoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $source = $this->model;
        $project = match (true) {
            $source instanceof Prompt => $source->project,
            $source instanceof EpisodeAssembly => $source->episodeRevision?->episode?->series?->project,
            $source instanceof SeriesAssembly => $source->series->project,
            default => null,
        };
        $receipt = $source instanceof Prompt || $source instanceof EpisodeAssembly || $source instanceof SeriesAssembly
            ? $source->publications->first() : null;

        return [
            'id' => $this->id,
            'label' => $source instanceof Prompt ? $source->prompt : $this->name,
            'url' => route('posts.tiktok.preview', ['channel' => $request->route('channel'), 'media' => $this->id]),
            'project' => $project instanceof Project ? Arr::only(ProjectResource::make($project)->resolve($request), ['id', 'name', 'cover']) : null,
            'receipt' => $receipt instanceof PublicationRequest ? [
                'id' => $receipt->id,
                'status' => $receipt->status->value,
                'submitted_at' => $receipt->submitted_at?->toISOString(),
                'published_at' => $receipt->published_at?->toISOString(),
                'url' => $receipt->post?->url,
            ] : null,
        ];
    }
}
