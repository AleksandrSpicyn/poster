<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Models\CharacterRevision;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GetCharacterReference
{
    public function run(CharacterRevision $revision, Media $media): string
    {
        abort_unless($media->model_type === $revision->getMorphClass()
            && $media->model_id === $revision->id
            && in_array($media->collection_name, [CharacterRevision::REFERENCES, CharacterRevision::VOICE_SAMPLE], true)
            && $media->disk === 'local', 404);

        $path = $media->getPath();
        abort_unless(is_file($path), 404);

        return $path;
    }
}
