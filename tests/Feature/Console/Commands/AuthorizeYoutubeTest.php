<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use Google\Service\YouTube;
use GuzzleHttp\Psr7\Response;

use function Tests\Support\consoleCommand;
use function Tests\Support\fakeGoogleClient;

test('YouTube сохраняет токен после обмена OAuth кода', /** @throws Exception */ function (int $status, array $payload, int $exitCode, ?string $token): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);
    $handler = fakeGoogleClient(new Response($status, [], json_encode($payload, JSON_THROW_ON_ERROR)));

    consoleCommand('youtube:auth', ['channel_id' => $channel->id])
        ->expectsQuestion('Paste the authorization code from the browse', 'test-code')
        ->assertExitCode($exitCode);

    expect($channel->refresh()->credentials['access_token'] ?? null)
        ->toBe($token)
        ->and($handler->count())
        ->toBe(0);
})->with([
    'успех' => [200, ['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 3600], 0, 'test-access'],
    'отказ' => [400, ['error' => 'invalid_grant', 'error_description' => 'Invalid code'], 1, null],
]);

test('дополнительная авторизация запрашивает чтение для проверки публикаций', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);
    fakeGoogleClient(new Response(200, [], json_encode(['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 3600], JSON_THROW_ON_ERROR)));

    consoleCommand('youtube:auth', ['channel_id' => $channel->id, '--with-read-verification' => true])
        ->expectsQuestion('Paste the authorization code from the browse', 'test-code')
        ->expectsOutputToContain(rawurlencode(YouTube::YOUTUBE_READONLY))
        ->assertSuccessful();

    expect($channel->refresh()->credentials['access_token'] ?? null)->toBe('test-access');
});
