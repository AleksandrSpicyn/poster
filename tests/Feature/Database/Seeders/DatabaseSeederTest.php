<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
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
