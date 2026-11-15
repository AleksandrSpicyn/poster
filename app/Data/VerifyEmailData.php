<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Container\Attributes\RouteParameter;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class VerifyEmailData extends Data
{
    public function __construct(
        #[FromRouteParameter('id'), Required]
        public string $id,
        #[FromRouteParameter('hash'), Required]
        public string $hash,
    ) {}

    public static function authorize(
        #[CurrentUser] User $user,
        #[RouteParameter('id')] string $id,
        #[RouteParameter('hash')] string $hash,
    ): bool {
        return hash_equals((string) $user->id, $id)
            && hash_equals(sha1($user->getEmailForVerification()), $hash);
    }
}
