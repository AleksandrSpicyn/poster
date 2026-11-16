<?php

declare(strict_types=1);

namespace App\Actions\Shots;

use App\Models\Prompt;
use App\Models\User;
use App\Tasks\Shots\CancelShotGeneration;
use App\Tasks\Users\RefreshUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

readonly class CancelShotGenerationAction
{
    public function __construct(private CancelShotGeneration $cancel, private RefreshUser $refreshUser) {}

    public function run(Prompt $prompt, User $actor): void
    {
        Gate::forUser($this->refreshUser->run($actor))->authorize('manageSeries');

        if ($prompt->shot_id === null) {
            throw ValidationException::withMessages(['generation' => 'Select a serial generation.']);
        }

        $this->cancel->run($prompt);
    }
}
