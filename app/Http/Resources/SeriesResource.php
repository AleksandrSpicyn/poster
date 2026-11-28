<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\SeriesAssetFormat;
use App\Enums\SeriesAudioMode;
use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Series
 * @property Series $resource
 */
class SeriesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assets' => $this->whenLoaded('media', fn(): array => $this->getMedia(Series::ASSETS)->map(fn(Media $asset): array => [
                'id' => $asset->id, 'label' => $asset->name, 'kind' => $asset->getCustomProperty('kind'),
                'format' => SeriesAssetFormat::of($asset)?->value,
                'extension' => pathinfo($asset->file_name, PATHINFO_EXTENSION),
                'size' => $asset->size,
                'url' => route('episodes.media.preview', $asset->id),
            ])->values()->all()),
            'project_id' => $this->project_id,
            'name' => $this->name,
            'output_mode' => $this->output_mode->value,
            'cycle_plan' => $this->cycle_plan,
            'cycle_paused' => $this->cycle_paused_at !== null,
            'specification' => ['audio_mode' => SeriesAudioMode::NATIVE->value, ...$this->specification],
            'specification_version' => $this->specification_version,
            'continuity_version' => $this->continuity_version,
            'lock_version' => $this->lock_version,
            'automation_enabled' => $this->automation_enabled,
            'auto_publish' => $this->auto_publish,
            'last_episode_number' => $this->whenHas('episodes_max_number'),
            'cast_settings' => $this->cast_settings?->toArray(),
            'episode_limit' => $this->episode_limit,
            'latest_production' => $this->whenLoaded('latestProductionRun', fn(): ?array => $this->latestProductionRun === null ? null : [
                ...ProductionRunResource::make($this->latestProductionRun)->resolve($request),
                'automatic_delivery' => $this->latestProductionRun->deliveryChannelIds() !== [] && $this->latestProductionRun->allowsAutomaticDelivery($this->resource),
            ]),
            'archived_at' => $this->archived_at?->toISOString(),
            'characters_count' => $this->whenCounted('characters'),
            'accepted_episodes_count' => $this->whenCounted('acceptedEpisodes'),
            'episodes_count' => $this->whenCounted('episodes'),
            'project' => $this->whenLoaded('project', fn(): array => ProjectResource::make($this->project)->resolve($request)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
