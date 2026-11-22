<?php

declare(strict_types=1);

namespace App\Providers;

use App\Classes\Generators\Codex\CodexProvider;
use App\Models\Channel;
use App\Models\Character;
use App\Models\CharacterRevision;
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
use App\Observers\BroadcastWorkspaceChanges;
use App\Policies\OperationsPolicy;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Ai;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::preventLazyLoading();

        Ai::extend('codex', fn(Application $app, array $config): CodexProvider => $app->make(CodexProvider::class, ['config' => $config]));

        Gate::define('viewLogViewer', [OperationsPolicy::class, 'viewLogs']);
        Gate::define('manageChannels', [OperationsPolicy::class, 'manageChannels']);
        Gate::define('manageSeries', [OperationsPolicy::class, 'manageSeries']);

        foreach ([Project::class, Channel::class, Prompt::class, Post::class, Series::class,
            Character::class, CharacterRevision::class, Episode::class, EpisodeRevision::class,
            Scene::class, Shot::class, GenerationOperation::class, EpisodeAssembly::class, SeriesAssembly::class,
            PublicationRequest::class, ProductionRun::class] as $model) {
            $model::observe(BroadcastWorkspaceChanges::class);
        }
    }
}
