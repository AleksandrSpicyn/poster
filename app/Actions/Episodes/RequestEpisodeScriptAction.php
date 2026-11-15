<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Data\Episodes\GenerateEpisodeScriptData;
use App\Jobs\GenerateEpisodeScriptJob;
use App\Models\Episode;
use App\Models\User;
use App\Tasks\Episodes\BuildEpisodeContext;
use App\Tasks\Episodes\QueueEpisodeScript;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

readonly class RequestEpisodeScriptAction
{
    public function __construct(private BuildEpisodeContext $context, private QueueEpisodeScript $queue) {}

    public function run(Episode $episode, GenerateEpisodeScriptData $data, User $actor): void
    {
        $actor = $actor->fresh();

        if ($actor === null) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries');

        $context = $this->context->run($episode);

        if ($context['characters'] === []) {
            throw ValidationException::withMessages(['characters' => 'Approve at least one character before requesting a screenplay.']);
        }

        if ($this->queue->run($episode, $data, $context, $actor->id)) {
            GenerateEpisodeScriptJob::dispatch($episode->id, $data->request_key)->afterCommit();
        }
    }
}
