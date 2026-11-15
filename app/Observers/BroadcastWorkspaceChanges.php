<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\WorkspaceUpdated;
use App\Models\Channel;
use App\Models\Post;
use App\Models\Project;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Model;

class BroadcastWorkspaceChanges
{
    public function saved(Model $model): void
    {
        if (! $model->wasRecentlyCreated && ! $model->wasChanged()) {
            return;
        }

        $this->broadcast($model);
    }

    public function deleted(Model $model): void
    {
        $this->broadcast($model);
    }

    private function broadcast(Model $model): void
    {
        $seriesOnly = ! ($model instanceof Project || $model instanceof Channel || $model instanceof Post
            || ($model instanceof Prompt && $model->shot_id === null));

        WorkspaceUpdated::dispatch($seriesOnly);
    }
}
