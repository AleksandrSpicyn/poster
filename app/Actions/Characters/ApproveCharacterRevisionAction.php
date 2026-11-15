<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Models\CharacterRevision;
use App\Models\User;
use App\Tasks\Characters\ApproveCharacterRevision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

readonly class ApproveCharacterRevisionAction
{
    public function __construct(private ApproveCharacterRevision $approveCharacterRevision) {}

    public function run(CharacterRevision $revision, User $user, int $expectedVersion): CharacterRevision
    {
        $user = $user->fresh();

        if ($user === null) {
            throw new AuthorizationException();
        }

        Gate::forUser($user)->authorize('manageSeries');

        return DB::transaction(fn(): CharacterRevision => $this->approveCharacterRevision->run($revision, $user, $expectedVersion));
    }
}
