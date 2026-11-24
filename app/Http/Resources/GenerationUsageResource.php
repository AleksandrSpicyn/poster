<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\Billing\GenerationUsageData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use stdClass;

/** @property GenerationUsageData $resource */
final class GenerationUsageResource extends JsonResource
{
    public static $wrap;

    /** @return array{enforced: bool, estimate: ?int, available: int, sources: Collection<int, stdClass>, shots?: list<array{shot_id: int, prompt_id: ?int, seconds: int, status: string}>} */
    public function toArray(Request $request): array
    {
        return [
            'enforced' => $this->resource->enforced,
            'estimate' => $this->resource->estimate,
            ...($this->resource->shots === null ? [] : ['shots' => $this->resource->shots]),
            'available' => $this->resource->available,
            'sources' => $this->resource->sources,
        ];
    }
}
