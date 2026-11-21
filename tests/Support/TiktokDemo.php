<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;

use function Pest\Laravel\withBasicAuth;

function authenticateDemoAuthor(string $author = 'author-a'): void
{
    config(['tiktok_demo.enabled' => true, 'tiktok_demo.authors' => [$author => password_hash('demo-test', PASSWORD_BCRYPT, ['cost' => 4])],
        'tiktok_demo.key' => 'sandbox-key', 'tiktok_demo.secret' => 'sandbox-secret', 'tiktok_demo.redirect' => 'https://demo.example/demo/tiktok/callback']);
    withBasicAuth($author, 'demo-test');
}

/** @return array{name:string,access:string,expires:string} */
function demoProfile(string $name): array
{
    return ['name' => $name, 'access' => Crypt::encryptString('test-access-' . $name), 'expires' => now()->addHour()->toIso8601String()];
}
