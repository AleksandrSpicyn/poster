<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;
use function Tests\Support\channelPayload;
use function Tests\Support\serialOperator;

test('пустой секрет сохраняет подключение и историю проектов', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE, 'client_id' => 'app-id', 'client_secret' => 'old-secret', 'credentials' => ['access_token' => 'old-token']]);
    $projectIds = $channel->projects()->allRelatedIds()->all();

    actingAs(serialOperator())->patch(route('channels.update', $channel), [...channelPayload(), 'client_secret' => '', 'is_active' => false])->assertSessionHasNoErrors()->assertRedirect(route('channels.edit', $channel));

    $channel->refresh();
    expect($channel->client_secret)->toBe('old-secret');
    expect($channel->credentials)->toBe(['access_token' => 'old-token']);
    expect($channel->is_active)->toBeFalse();
    expect($channel->projects()->allRelatedIds()->all())->toBe($projectIds);
    Http::assertNothingSent();
});

test('смена приложения сбрасывает OAuth и прежний секрет', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'old-token']]);

    actingAs(serialOperator())->patch(route('channels.update', $channel), [...channelPayload(), 'client_id' => 'new-app', 'client_secret' => ''])->assertSessionHasNoErrors();

    expect($channel->refresh()->credentials)->toBeNull();
    expect($channel->client_secret)->toBeNull();
});

test('замена секрета требует нового подключения', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE, 'client_id' => 'app-id', 'credentials' => ['access_token' => 'old-token']]);

    actingAs(serialOperator())->patch(route('channels.update', $channel), channelPayload())->assertSessionHasNoErrors();

    expect($channel->refresh()->credentials)->toBeNull();
    expect($channel->client_secret)->toBe('app-secret');
});

test('платформа существующего канала неизменяема', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);

    actingAs(serialOperator())->patchJson(route('channels.update', $channel), [...channelPayload(), 'type' => 'instagram'])->assertUnprocessable()->assertJsonValidationErrors('type');

    expect($channel->refresh()->type)->toBe(ChannelType::YOUTUBE);
});

test('недопущенный пользователь не управляет каналами', function (): void {
    $channel = Channel::factory()->create();
    actingAs(User::factory()->create())->patch(route('channels.update', $channel), channelPayload())->assertForbidden();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    $channel = Channel::factory()->create();
    patch(route('channels.update', $channel), channelPayload())->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->patch(route('channels.update', $channel), channelPayload())->assertRedirect(route('verification.notice'));
});

test('отсутствие флага активности не включает выключенный канал', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE, 'is_active' => false]);
    $payload = channelPayload();
    unset($payload['is_active']);

    actingAs(serialOperator())->patch(route('channels.update', $channel), $payload)->assertSessionHasNoErrors();

    expect($channel->refresh()->is_active)->toBeFalse();
});
