<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Classes\OpusClip\VideoReference;
use App\Enums\ChannelType;
use App\Models\OpusClip;
use App\Models\OpusClipMetric;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OpusClip */
class OpusClipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clip_id' => $this->clip_id,
            'title' => $this->title,
            'source_url' => 'https://clip.opus.pro/clip/' . rawurlencode($this->source->external_id),
            'source' => $this->source->title ?? 'Источник',
            'game' => $this->source->game,
            'duration_ms' => $this->duration_ms,
            'score' => $this->score,
            'publications' => collect($this->publications)->map(static function (array $post): array {
                $platform = ChannelType::tryFrom(strtolower($post['platform']));
                $url = $post['url'];

                return [...$post, 'media_id' => $platform !== null && is_string($url) ? VideoReference::fromUrl($platform, $url) : null];
            })->values()->all(),
            'observations' => $this->latestMetrics->map(fn(OpusClipMetric $row): array => [
                'platform' => $row->platform,
                'media_id' => $row->media_id,
                'date' => $row->observed_on,
                'metrics' => $row->metrics,
            ])->all(),
        ];
    }
}
