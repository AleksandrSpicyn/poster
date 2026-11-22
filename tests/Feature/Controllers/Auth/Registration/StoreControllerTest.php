<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;

test('прямой запрос регистрации отклоняется', function (): void {
    Event::fake([Registered::class]);
    Notification::fake();

    post(route('register'), ['name' => 'Test', 'email' => 'user@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertMethodNotAllowed();

    assertGuest();
    assertDatabaseCount('users', 0);
    Event::assertNotDispatched(Registered::class);
    Notification::assertNothingSent();
});
