<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('workspace', fn(User $user): bool => $user->hasVerifiedEmail());
Broadcast::channel('workspace.series', fn(User $user): bool => $user->hasVerifiedEmail() && Gate::forUser($user)->allows('manageSeries'));
