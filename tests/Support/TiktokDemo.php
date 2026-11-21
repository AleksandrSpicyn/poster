<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;

use function Pest\Laravel\withSession;

function authenticateDemoAuthor(string $author = 'author-a'): void
{
    config(['tiktok_demo.enabled' => true, 'tiktok_demo.key' => 'sandbox-key', 'tiktok_demo.secret' => 'sandbox-secret', 'tiktok_demo.redirect' => 'https://demo.example/demo/tiktok/callback']);
    withSession(['tiktok_demo.owner' => $author, 'tiktok_demo.mode' => 'sandbox']);
}

/** @return array{name:string,access:string,expires:string} */
function demoProfile(string $name): array
{
    return ['name' => $name, 'access' => Crypt::encryptString('test-access-' . $name), 'expires' => now()->addHour()->toIso8601String()];
}
