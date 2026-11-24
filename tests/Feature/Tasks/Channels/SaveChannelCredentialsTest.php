<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Tasks\Channels\SaveChannelCredentials;

test('обновление токена сохраняет аккаунт и refresh token', function (): void {
    $channel = Channel::factory()->create(['credentials' => ['access_token' => 'old', 'refresh_token' => 'refresh', 'account_name' => 'Wildlife']]);

    app(SaveChannelCredentials::class)->run($channel, ['access_token' => 'new'], preserveMetadata: true);

    expect($channel->refresh()->credentials)->toMatchArray(['access_token' => 'new', 'refresh_token' => 'refresh', 'account_name' => 'Wildlife']);
});

test('запоздавший refresh не восстанавливает отключённое подключение', function (): void {
    $channel = Channel::factory()->create(['credentials' => ['access_token' => 'old']]);
    $stale = clone $channel;
    $channel->update(['credentials' => null]);

    expect(fn() => app(SaveChannelCredentials::class)->run($stale, ['access_token' => 'new'], preserveMetadata: true))->toThrow(RuntimeException::class);

    expect($channel->refresh()->credentials)->toBeNull();
});

test('поздний токен не заменяет связь с новым аккаунтом', function (): void {
    $channel = Channel::factory()->create(['external_account' => 'old', 'credentials' => ['access_token' => 'active']]);
    $stale = clone $channel;
    $channel->update(['external_account' => 'replacement']);

    expect(fn() => app(SaveChannelCredentials::class)->run($stale, ['access_token' => 'late']))->toThrow(RuntimeException::class);
    expect($channel->refresh()->credentials)->toBe(['access_token' => 'active']);
});
