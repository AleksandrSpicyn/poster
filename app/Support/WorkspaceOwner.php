<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Channel;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\ClippingConnection;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\GenerationOperation;
use App\Models\Post;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\Scene;
use App\Models\Series;
use App\Models\SeriesAssembly;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class WorkspaceOwner
{
    public static function id(?Model $model, ?Model $boundParent = null): ?int
    {
        if ($model instanceof Project || $model instanceof Channel || $model instanceof ClippingConnection) {
            return $model->owner_id;
        }

        $relation = match (true) {
            $model instanceof Media => 'model',
            $model instanceof Post, $model instanceof PublicationRequest => 'channel',
            $model instanceof Prompt, $model instanceof ProductionRun, $model instanceof Series => 'project',
            $model instanceof Character, $model instanceof Episode, $model instanceof SeriesAssembly => 'series',
            $model instanceof EpisodeAssembly, $model instanceof Scene => 'episodeRevision',
            $model instanceof EpisodeRevision => 'episode',
            $model instanceof CharacterRevision => 'character',
            $model instanceof Shot => 'scene',
            $model instanceof GenerationOperation => 'prompt',
            default => null,
        };

        if ($relation === null) {
            return null;
        }

        if ($boundParent !== null && ! $model->relationLoaded($relation)) {
            $link = $model->{$relation}();

            if ($link->getRelated()::class === $boundParent::class
                && $model->getAttribute($link->getForeignKeyName()) === $boundParent->getAttribute($link->getOwnerKeyName())) {
                $model->setRelation($relation, $boundParent);
            }
        }

        $parent = $model->getRelationValue($relation);

        return $parent instanceof Model ? self::id($parent) : null;
    }
}
