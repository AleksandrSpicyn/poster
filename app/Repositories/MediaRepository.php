<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CharacterRevision;
use App\Models\Prompt;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @extends EloquentRepository<Media> */
readonly class MediaRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Media::class;
    }

    public function characterReference(int $revisionId, int $mediaId): Media
    {
        return Media::query()->where('model_type', new CharacterRevision()->getMorphClass())
            ->where('model_id', $revisionId)->where('collection_name', CharacterRevision::REFERENCES)
            ->findOrFail($mediaId);
    }

    public function shotVideo(int $id): Media
    {
        return Media::query()->where('collection_name', Prompt::SHOT_VIDEO)->findOrFail($id);
    }

    public function referenceForRevision(CharacterRevision $revision, int $id): Media
    {
        return $revision->media()
            ->where('collection_name', CharacterRevision::REFERENCES)
            ->findOrFail($id);
    }
}
