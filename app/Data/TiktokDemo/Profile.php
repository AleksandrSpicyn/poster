<?php

declare(strict_types=1);

namespace App\Data\TiktokDemo;

use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

final readonly class Profile
{
    private function __construct(public string $name, private string $encryptedToken, private string $expires) {}

    public static function fromSession(Store $session, string $author): ?self
    {
        $profile = $session->get("tiktok_demo.$author.profile");

        if (! is_array($profile) || ! is_string($profile['name'] ?? null) || ! is_string($profile['access'] ?? null) || ! is_string($profile['expires'] ?? null)) {
            return null;
        }

        return new self($profile['name'], $profile['access'], $profile['expires']);
    }

    public function expired(): bool
    {
        return Carbon::parse($this->expires)->isPast();
    }

    public function token(): string
    {
        return Crypt::decryptString($this->encryptedToken);
    }
}
