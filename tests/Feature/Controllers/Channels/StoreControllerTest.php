<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\post;
use function Tests\Support\channelPayload;
use function Tests\Support\serialOperator;

test('новый канал сохраняется без проекта и внешних запросов', function (): void {
    actingAs(serialOperator())->post(route('channels.store'), [...channelPayload(), 'is_active' => false])->assertSessionHasNoErrors()->assertRedirect();

    $channel = Channel::query()->sole();
    expect($channel->name)->toBe('Wildlife YouTube');
    expect($channel->client_secret)->toBe('app-secret');
    expect($channel->is_active)->toBeFalse();
    expect($channel->credentials)->toBeNull();
    expect($channel->project_id)->toBeNull();
    expect($channel->projects()->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('невалидные настройки не создают канал', function (array $changes, string $field): void {
    actingAs(serialOperator())->postJson(route('channels.store'), [...channelPayload(), ...$changes])->assertUnprocessable()->assertJsonValidationErrors($field);

    assertDatabaseCount('channels', 0);
    Http::assertNothingSent();
})->with([
    'название' => [['name' => ''], 'name'],
    'длина названия' => [['name' => str_repeat('a', 256)], 'name'],
    'платформа' => [['type' => 'unknown'], 'type'],
    'неподдерживаемая платформа' => [['type' => 'vk'], 'type'],
    'тип идентификатора' => [['client_id' => ['invalid']], 'client_id'],
    'длина секрета' => [['client_secret' => str_repeat('x', 2049)], 'client_secret'],
    'флаг активности' => [['is_active' => 'invalid'], 'is_active'],
]);

test('ошибка валидации не помещает секрет в старый ввод', function (): void {
    actingAs(serialOperator())->from(route('channels.create'))->post(route('channels.store'), [...channelPayload(), 'name' => ''])
        ->assertSessionHasErrors('name')->assertSessionMissing('_old_input.client_secret');
});

test('недопущенный пользователь не управляет каналами', function (): void {
    actingAs(User::factory()->create())->post(route('channels.store'), channelPayload())->assertForbidden();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    post(route('channels.store'), channelPayload())->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->post(route('channels.store'), channelPayload())->assertRedirect(route('verification.notice'));
});
