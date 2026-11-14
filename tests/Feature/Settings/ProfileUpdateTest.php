<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

use function Pest\Laravel\assertGuest;

test('владелец видит настройки профиля', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->get('/settings/profile');

    $response->assertOk()->assertInertia(fn(\Inertia\Testing\AssertableInertia $page) => $page->component('settings/Profile'));
});

test('изменение email сбрасывает подтверждение', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('сохранение прежнего email не сбрасывает подтверждение', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('удаление профиля завершает сессию и удаляет пользователя', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    assertGuest();
    expect($user->fresh())->toBeNull();
});

test('неверный пароль запрещает удаление профиля', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/settings/profile')
        ->delete('/settings/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($user->fresh())->not->toBeNull();
});
