<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\serialOperator;

test('OAuth запрашивает нужные права без передачи секрета в браузер', function (ChannelType $type, string $host, string $scope): void {
    $channel = Channel::factory()->create(['type' => $type, 'client_secret' => 'never-expose-client-secret-1234567890']);

    $response = actingAs(serialOperator())->post(route('channels.connect', $channel), [], ['X-Inertia' => 'true'])->assertStatus(409);

    $url = (string) $response->headers->get('X-Inertia-Location');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
    expect(parse_url($url, PHP_URL_HOST))->toBe($host);
    expect($params['redirect_uri'])->toBe(route('channels.callback', $channel));
    expect($params['scope'])->toContain($scope);
    expect($params['state'])->toHaveLength(64);
    expect($params)->not->toHaveKey('client_secret');
    expect($url)->not->toContain((string) $channel->client_secret);
    Http::assertNothingSent();
})->with([
    'YouTube' => [ChannelType::YOUTUBE, 'accounts.google.com', 'youtube.upload'],
    'Instagram' => [ChannelType::INSTAGRAM, 'www.instagram.com', 'instagram_business_content_publish'],
]);

test('неполные настройки не начинают OAuth', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE, 'client_secret' => null]);

    actingAs(serialOperator())->post(route('channels.connect', $channel))->assertSessionHasErrors('oauth');

    Http::assertNothingSent();
});

test('недопущенный пользователь не управляет каналами', function (): void {
    $channel = Channel::factory()->create();
    actingAs(User::factory()->create())->post(route('channels.connect', $channel))->assertForbidden();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    $channel = Channel::factory()->create();
    post(route('channels.connect', $channel))->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->post(route('channels.connect', $channel))->assertRedirect(route('verification.notice'));
});
