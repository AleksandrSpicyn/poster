<?php

declare(strict_types=1);

namespace App\Actions\TiktokDemo;

use Illuminate\Session\Store;
use Illuminate\Support\Str;

final readonly class ConnectAction
{
    public const array SCOPES = ['user.info.basic', 'video.list', 'video.upload'];

    public function __construct(private Store $session) {}

    public function run(string $author): string
    {
        $redirect = config('tiktok_demo.redirect');
        abort_unless(filled(config('tiktok_demo.key')) && filled(config('tiktok_demo.secret')) && is_string($redirect) && str_starts_with($redirect, 'https://'), 503, 'Укажите настройки TikTok Sandbox.');
        $state = Str::random(64);
        $this->session->put('tiktok_demo.state', ['author' => $author, 'value' => $state, 'expires' => now()->addMinutes(10)->timestamp]);

        return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key' => config('tiktok_demo.key'), 'scope' => implode(',', self::SCOPES),
            'response_type' => 'code', 'redirect_uri' => $redirect, 'state' => $state,
        ]);
    }
}
