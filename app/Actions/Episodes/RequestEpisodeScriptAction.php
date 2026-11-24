<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Data\Episodes\GenerateEpisodeScriptData;
use App\Enums\GenerationAccountingMode;
use App\Jobs\GenerateEpisodeScriptJob;
use App\Models\Episode;
use App\Models\User;
use App\Repositories\BillingAccountingRepository;
use App\Tasks\Episodes\BuildEpisodeContext;
use App\Tasks\Episodes\QueueEpisodeScript;
use App\Tasks\Users\RefreshUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

readonly class RequestEpisodeScriptAction
{
    public function __construct(private BillingAccountingRepository $accounting, private BuildEpisodeContext $context, private QueueEpisodeScript $queue, private RefreshUser $refreshUser) {}

    public function run(Episode $episode, GenerateEpisodeScriptData $data, User $actor): void
    {
        $actor = $this->refreshUser->run($actor);

        if (! $actor instanceof User) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries', $episode);

        if ($this->accounting->storedMode($actor->id) === GenerationAccountingMode::ENFORCED->value) {
            throw ValidationException::withMessages(['balance' => 'Генерация сценария входит в резерв эпизода. Запустите производство из проекта или введите сценарий вручную.']);
        }

        $context = $this->context->run($episode);

        if ($context['characters'] === []) {
            throw ValidationException::withMessages(['characters' => 'Approve at least one character before requesting a screenplay.']);
        }

        if ($this->queue->run($episode, $data, $context, $actor->id)) {
            GenerateEpisodeScriptJob::dispatch($episode->id, $data->request_key)->afterCommit();
        }
    }
}
