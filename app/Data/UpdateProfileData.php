<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Lowercase;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\References\AuthenticatedUserReference;

class UpdateProfileData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required, Lowercase, Email, Max(255), Unique(User::class, ignore: new AuthenticatedUserReference())]
        public string $email,
    ) {}

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Введите имя.',
            'name.string' => 'Введите имя.',
            'name.max' => 'Имя не должно превышать :max символов.',
            'email.required' => 'Введите адрес электронной почты.',
            'email.string' => 'Введите адрес электронной почты.',
            'email.email' => 'Введите корректный адрес электронной почты.',
            'email.lowercase' => 'Введите адрес электронной почты строчными буквами.',
            'email.max' => 'Адрес электронной почты не должен превышать :max символов.',
            'email.unique' => 'Этот адрес электронной почты уже используется.',
        ];
    }
}
