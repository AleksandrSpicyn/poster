<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Tests\Support\serialOperator;

test('оператор подписывается на приватные статусы сериала', function (): void {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-id']);
    require base_path('routes/channels.php');

    actingAs(serialOperator())->postJson(route('broadcasting.auth'), [
        'socket_id' => '123.456', 'channel_name' => 'private-workspace.series',
    ])->assertOk()->assertJsonStructure(['auth']);
});

test('читатель получает общие статусы без доступа к сериалам', function (): void {
    serialOperator();
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-id']);
    require base_path('routes/channels.php');
    $reader = User::factory()->create();

    actingAs($reader)->postJson(route('broadcasting.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-workspace'])
        ->assertOk()->assertJsonStructure(['auth']);
    actingAs($reader)->postJson(route('broadcasting.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-workspace.series'])
        ->assertForbidden();
});

test('гость и неподтверждённый пользователь не подписываются', function (): void {
    postJson(route('broadcasting.auth'), ['socket_id' => '123.456', 'channel_name' => 'private-workspace'])->assertUnauthorized();

    actingAs(User::factory()->unverified()->create())->postJson(route('broadcasting.auth'), [
        'socket_id' => '123.456', 'channel_name' => 'private-workspace',
    ])->assertForbidden();
});
