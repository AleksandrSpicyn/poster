<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @mixin Media */
class CharacterReferenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->getCustomProperty('label', $this->name),
            'view' => $this->getCustomProperty('view', 'other'),
            'checksum' => $this->getCustomProperty('checksum'),
            'url' => route('characters.references.show', ['revision' => $this->model_id, 'media' => $this->id]),
        ];
    }
}
