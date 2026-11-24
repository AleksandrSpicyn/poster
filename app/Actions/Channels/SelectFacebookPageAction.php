<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Classes\Channels\FacebookOAuth;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use App\Repositories\ChannelRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SelectFacebookPageAction
{
    public function __construct(private FacebookOAuth $oauth, private ChannelRepository $channels) {}

    public function run(User $user, Channel $channel, string $page): void
    {
        DB::transaction(function () use ($user, $channel, $page): void {
            $current = $this->channels->findLockedOrFail($channel->id);
            abort_unless($current->owner_id === $user->id && $current->type === ChannelType::FACEBOOK, 404);
            $credentials = $this->oauth->select($current, $page);

            try {
                $this->channels->update($current, ['external_account' => $page, 'credentials' => $credentials]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['oauth' => 'Эта страница уже подключена к каналу.']);
            }
        });
    }
}
