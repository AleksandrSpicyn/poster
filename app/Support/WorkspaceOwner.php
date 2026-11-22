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
    public static function id(?Model $model): ?int
    {
        return match (true) {
            $model instanceof Project, $model instanceof Channel, $model instanceof ClippingConnection => $model->owner_id,
            $model instanceof Media => self::id($model->model),
            $model instanceof Post, $model instanceof PublicationRequest => self::id($model->channel),
            $model instanceof Prompt, $model instanceof ProductionRun, $model instanceof Series => self::id($model->project),
            $model instanceof Character, $model instanceof Episode, $model instanceof SeriesAssembly => self::id($model->series),
            $model instanceof EpisodeAssembly, $model instanceof Scene => self::id($model->episodeRevision),
            $model instanceof EpisodeRevision => self::id($model->episode),
            $model instanceof CharacterRevision => self::id($model->character),
            $model instanceof Shot => self::id($model->scene),
            $model instanceof GenerationOperation => self::id($model->prompt),
            default => null,
        };
    }
}
