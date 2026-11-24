<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Container\Attributes\Config;
use Inertia\Response;

final class ContactsController
{
    /**
     * Контакты студии.
     *
     * @group Публичные страницы
     * @unauthenticated
     */
    public function __invoke(#[Config('contacts.email')] ?string $email, #[Config('contacts.telegram')] ?string $telegram): Response
    {
        return inertia('Contacts', [
            'email' => trim($email ?? ''),
            'telegram' => ltrim(trim($telegram ?? ''), '@'),
        ]);
    }
}
