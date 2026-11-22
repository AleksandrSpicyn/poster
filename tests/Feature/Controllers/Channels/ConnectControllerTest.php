<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('OAuth запрашивает нужные права без передачи секрета в браузер', function (ChannelType $type, string $host, string $scope): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => $type, 'client_secret' => 'never-expose-client-secret-1234567890']);

    $response = actingAs(serialOperator())->post(route('channels.connect', $channel), [], ['X-Inertia' => 'true'])->assertStatus(409);

    $url = (string) $response->headers->get('X-Inertia-Location');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
    expect(parse_url($url, PHP_URL_HOST))->toBe($host);
    expect($params['redirect_uri'])->toBe($type === ChannelType::TIKTOK ? route('channels.tiktok-redirect') : route('channels.provider-callback', $type));
    expect($params['scope'])->toContain($scope);

    if ($type === ChannelType::TIKTOK) {
        expect($params['scope'])->toBe('user.info.basic,video.list,video.upload');
        expect($params['client_key'] ?? null)->toBe($channel->oauthClientId());
        expect($params)->not->toHaveKey('client_id');
    }

    expect($params['state'])->toHaveLength(64);
    expect($params)->not->toHaveKey('client_secret');
    expect($url)->not->toContain((string) $channel->client_secret);
    Http::assertNothingSent();
})->with([
    'YouTube' => [ChannelType::YOUTUBE, 'accounts.google.com', 'youtube.upload'],
    'TikTok' => [ChannelType::TIKTOK, 'www.tiktok.com', 'video.upload'],
    'Instagram' => [ChannelType::INSTAGRAM, 'www.instagram.com', 'instagram_business_content_publish'],
]);

test('игровой TikTok запрашивает тот же набор прав, что и канал сериалов', function (?string $externalAccount): void {
    $project = Project::factory()->recycle(workspaceOwner())->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $channel = Channel::factory()->recycle(workspaceOwner())->for($project)->create(['type' => ChannelType::TIKTOK, 'external_account' => $externalAccount]);

    $response = actingAs(serialOperator())->post(route('channels.connect', $channel), [], ['X-Inertia' => 'true'])->assertStatus(409);

    parse_str((string) parse_url((string) $response->headers->get('X-Inertia-Location'), PHP_URL_QUERY), $params);
    expect($params['scope'])->toBe('user.info.basic,video.list,video.upload');
    Http::assertNothingSent();
})->with([[null], ['fixture-owner']]);

test('неполные настройки не начинают OAuth', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE, 'client_secret' => null]);

    actingAs(serialOperator())->post(route('channels.connect', $channel))->assertSessionHasErrors('oauth');

    Http::assertNothingSent();
});

test('пользователь не управляет чужим каналом', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create();
    actingAs(User::factory()->create())->post(route('channels.connect', $channel))->assertNotFound();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create();
    post(route('channels.connect', $channel))->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->post(route('channels.connect', $channel))->assertRedirect(route('verification.notice'));
});

test('для клиппинга запрашивает аналитику без публикации', function (ChannelType $type, string $scope): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => $type, 'external_account' => 'fixture-owner']);

    $response = actingAs(serialOperator())->post(route('channels.connect', $channel), [], ['X-Inertia' => 'true'])->assertStatus(409);

    parse_str((string) parse_url((string) $response->headers->get('X-Inertia-Location'), PHP_URL_QUERY), $params);
    expect($params['scope'])->toBe($scope);
    Http::assertNothingSent();
})->with([
    [ChannelType::YOUTUBE, 'https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/yt-analytics.readonly'],
    [ChannelType::INSTAGRAM, 'instagram_business_basic,instagram_business_manage_insights'],
]);

test('включение Direct Post добавляет разрешение к запросу Login Kit', function (): void {
    config(['services.tiktok.direct_post' => true]);
    $user = workspaceOwner();
    $channel = Channel::factory()->recycle($user)->create(['type' => ChannelType::TIKTOK]);
    actingAs($user);

    $response = post(route('channels.connect', $channel))->assertRedirect();
    $query = parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY);
    parse_str((string) $query, $parameters);

    expect(explode(',', is_string($parameters['scope']) ? $parameters['scope'] : ''))->toContain('video.publish', 'video.upload', 'video.list')
        ->and($parameters['redirect_uri'])->toBe(route('channels.tiktok-redirect'));
});
