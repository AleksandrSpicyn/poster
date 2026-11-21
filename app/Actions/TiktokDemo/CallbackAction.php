<?php

declare(strict_types=1);

namespace App\Actions\TiktokDemo;

use App\Classes\Tiktok\TiktokClient;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Support\Integrations\Payload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final readonly class CallbackAction
{
    public function __construct(private TiktokClient $client, private Store $session) {}

    public function run(string $author, mixed $state, mixed $code): void
    {
        $pending = $this->session->pull('tiktok_demo.state');
        abort_unless(is_array($pending) && $pending['author'] === $author && $pending['expires'] > now()->timestamp
            && is_string($pending['value'] ?? null) && is_string($state) && hash_equals($pending['value'], $state), 403);

        $key = "tiktok_demo.$author";

        if (! is_string($code) || $code === '') {
            $this->session->flash("$key.notice", 'Подключение отменено.');

            return;
        }

        try {
            // Explicit Sandbox credentials on an unsaved adapter for the shared Login Kit exchange.
            $channel = new Channel(['type' => ChannelType::TIKTOK, 'client_id' => Payload::string(config('tiktok_demo.key'), 'key'),
                'client_secret' => Payload::string(config('tiktok_demo.secret'), 'secret')]);
            $tokens = $this->client->exchange($channel, $code, Payload::string(config('tiktok_demo.redirect'), 'redirect'), ConnectAction::SCOPES);
            $this->session->put("$key.profile", [
                'name' => Payload::string($tokens['account_name'], 'name'),
                'access' => Crypt::encryptString(Payload::string($tokens['access_token'], 'token')),
                'expires' => Payload::string($tokens['expires_at'], 'expiry'),
            ]);
            $this->session->forget("$key.operation");
        } catch (ConnectionException|RuntimeException) {
            $this->session->flash("$key.notice", 'Подключение не удалось. Проверьте разрешения и повторите вход.');
        }
    }
}
