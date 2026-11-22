<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use UnexpectedValueException;

/** @implements CastsAttributes<array<array-key, mixed>|null, array<array-key, mixed>|null> */
class ChannelCredentials implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException('Invalid channel credentials.');
        }

        $stored = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        if (is_array($stored) && is_string($stored['encrypted_payload'] ?? null)) {
            $stored = json_decode(Crypt::decryptString($stored['encrypted_payload']), true, flags: JSON_THROW_ON_ERROR);
        }

        if (! is_array($stored)) {
            throw new UnexpectedValueException('Invalid channel credentials.');
        }

        return $stored;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode([
            'encrypted_payload' => Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)),
            'access_token' => filled($value['access_token'] ?? null) ? true : null,
            'scope' => $value['scope'] ?? null,
        ], JSON_THROW_ON_ERROR);
    }
}
