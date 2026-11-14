<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\seed;

test('повторное заполнение базы не дублирует проект каналы и администратора', function (): void {
    config(['services.admin.email' => 'admin@example.com', 'services.admin.password' => 'original-password']);

    seed(DatabaseSeeder::class);
    config(['services.admin.password' => 'replacement-password']);
    seed(DatabaseSeeder::class);

    assertDatabaseCount('projects', 1);
    assertDatabaseCount('channels', 3);
    assertDatabaseCount('users', 1);
    expect(Hash::check('original-password', User::query()->sole()->password))->toBeTrue();
});

test('отсутствующие учётные данные не создают администратора', function (): void {
    config(['services.admin.email' => '', 'services.admin.password' => '']);

    expect(fn() => app()->call([app(UserSeeder::class), 'run']))->toThrow(RuntimeException::class);

    assertDatabaseCount('users', 0);
});
