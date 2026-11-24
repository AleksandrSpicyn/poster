<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\SeriesOutputMode;
use App\Models\EpisodeAssembly;
use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Series */
class SeriesAssemblyParametersResource extends JsonResource
{
    /** @return array{parts: list<array{id: int|null, number: int, title: string|null, version: int|null, ready: bool}>, can_build: bool} */
    public function toArray(Request $request): array
    {
        $episodes = $this->episodes->keyBy('number');
        $limit = $this->episode_limit ?? $episodes->count();
        $parts = [];
        $canBuild = $this->project?->is_active === true && $this->release_started_at === null && $this->archived_at === null
            && $this->cycle_paused_at === null && $this->output_mode === SeriesOutputMode::SINGLE_VIDEO && $this->cycle_plan !== null
            && $limit > 0 && $limit === $episodes->count();

        for ($number = 1; $number <= $limit; $number++) {
            $episode = $episodes->get($number);
            $accepted = $episode?->acceptedAssembly;
            $media = $accepted?->getFirstMedia(EpisodeAssembly::VIDEO);
            $ready = $episode?->validity === EpisodeValidity::CURRENT && $accepted?->status === AssemblyStatus::ACCEPTED
                && $accepted->duration_ms !== null && $media !== null && is_file($media->getPath());
            $parts[] = [
                'id' => $episode?->id,
                'number' => $number,
                'title' => $episode?->title,
                'version' => $accepted?->number,
                'ready' => $ready,
            ];
            $canBuild = $canBuild && $ready;
        }

        return ['parts' => $parts, 'can_build' => $canBuild];
    }
}
