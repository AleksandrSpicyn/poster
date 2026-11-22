<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('workspace.{owner}', fn(User $user, string $owner): bool => $user->hasVerifiedEmail() && (string) $user->id === $owner);
