<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Container\Attributes\Config;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(
        #[Config('services.admin.email')] string $email,
        #[Config('services.admin.password')] string $password,
    ): void {
        if ($email === '' || $password === '') {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD before seeding an admin.');
        }

        User::query()->firstOrCreate(
            [
                'email' => $email,
            ],
            [
                'password' => Hash::make($password),
                'name' => 'Admin',
            ],
        );
    }
}
