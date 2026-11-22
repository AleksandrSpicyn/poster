<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;

class PublicationActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $this->resource instanceof stdClass ? get_object_vars($this->resource) : [];

        return $attributes;
    }
}
