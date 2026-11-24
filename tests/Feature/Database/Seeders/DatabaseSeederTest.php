<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\seed;

test('повторное заполнение базы не дублирует проект каналы и администратора', function (): void {
    config(['services.admin.email' => 'admin@example.com', 'services.admin.password' => 'original-password']);

    config([
        'services.instagram.channel_id' => 'fixture-account',
        'services.instagram.token' => 'fixture-token',
    ]);

    seed(DatabaseSeeder::class);
    config(['services.instagram.channel_id' => 'another-account']);
    config(['services.admin.password' => 'replacement-password']);
    seed(DatabaseSeeder::class);

    assertDatabaseCount('projects', 1);
    assertDatabaseCount('channels', 3);
    assertDatabaseCount('users', 1);

    $instagram = Channel::query()->where('type', ChannelType::INSTAGRAM)->sole();
    expect($instagram->credentials)->toMatchArray(['channel_id' => 'fixture-account', 'access_token' => 'fixture-token']);

    expect(Hash::check('original-password', User::query()->sole()->password))->toBeTrue();
});
