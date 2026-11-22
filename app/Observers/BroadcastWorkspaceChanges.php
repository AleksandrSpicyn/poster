<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\WorkspaceUpdated;
use Illuminate\Database\Eloquent\Model;

class BroadcastWorkspaceChanges
{
    public function saved(Model $model): void
    {
        if (! $model->wasRecentlyCreated && ! $model->wasChanged()) {
            return;
        }

        WorkspaceUpdated::forResource($model);
    }

    public function deleted(Model $model): void
    {
        WorkspaceUpdated::forResource($model);
    }
}
