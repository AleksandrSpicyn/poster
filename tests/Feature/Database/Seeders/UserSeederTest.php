<?php

declare(strict_types=1);

use Database\Seeders\UserSeeder;

use function Pest\Laravel\assertDatabaseCount;

test('отсутствующие учётные данные не создают администратора', function (): void {
    config(['services.admin.email' => '', 'services.admin.password' => '']);

    expect(fn() => app()->call([app(UserSeeder::class), 'run']))->toThrow(RuntimeException::class);

    assertDatabaseCount('users', 0);
});
