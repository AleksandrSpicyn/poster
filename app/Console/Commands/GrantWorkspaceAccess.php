<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\UserRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class GrantWorkspaceAccess extends Command
{
    protected $signature = 'workspace:grant {email}';

    protected $description = 'Send a one-time password setup link to the workspace email address';

    public function handle(UserRepository $users): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }

        [$user, $token] = DB::transaction(function () use ($users, $email): array {
            $user = $users->forInvitation($email);

            return [$user, Password::createToken($user)];
        });

        $user->sendPasswordResetNotification($token);
        $this->info('Password setup link sent.');

        return self::SUCCESS;
    }
}
