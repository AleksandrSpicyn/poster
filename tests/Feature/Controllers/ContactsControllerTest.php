<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

test('гость видит контакты из конфигурации', function (?string $email, ?string $telegram, string $expectedTelegram): void {
    config(['contacts.email' => $email, 'contacts.telegram' => $telegram]);

    get(route('contacts'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Contacts')
            ->where('auth.user', null)
            ->where('email', $email ?? '')
            ->where('telegram', $expectedTelegram));
})->with([
    'оба контакта' => ['hello@example.test', '@poster_test', 'poster_test'],
    'только Telegram' => [null, 'poster_test', 'poster_test'],
    'только email' => ['hello@example.test', '', ''],
    'пустая конфигурация' => [null, null, ''],
]);
