<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\User;
use App\Tasks\Episodes\BuildEpisodeContext;
use App\Tasks\Episodes\GenerateEpisodeScript;
use App\Tasks\Episodes\SaveEpisodeScript;
use App\Tasks\Users\RefreshUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

readonly class GenerateEpisodeScriptAction
{
    public function __construct(
        private BuildEpisodeContext $context,
        private GenerateEpisodeScript $generate,
        private SaveEpisodeScript $save,
        private RefreshUser $refreshUser,
    ) {}

    public function run(Episode $episode, string $brief, int $expectedVersion, User $actor): EpisodeRevision
    {
        $actor = $this->refreshUser->run($actor);

        if (! $actor instanceof User) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries', $episode);

        $context = $this->context->run($episode);

        if ($context['characters'] === []) {
            throw ValidationException::withMessages(['characters' => 'Approve at least one character before generating a screenplay.']);
        }

        $script = $this->generate->run($context, $brief);

        return $this->save->run($episode, $script, $expectedVersion, $context);
    }
}
