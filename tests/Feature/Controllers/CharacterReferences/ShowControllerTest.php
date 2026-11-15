<?php

declare(strict_types=1);

use App\Models\CharacterRevision;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\serialReferenceMedia;

test('авторизованный пользователь читает приватный референс', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $media = serialReferenceMedia($context['revision']);

    $response = actingAs(serialOperator())->get(route('characters.references.show', ['revision' => $context['revision'], 'media' => $media]));

    $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->hasCacheControlDirective('private'))->toBeTrue();
    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
});

test('приватный референс недоступен гостю', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $media = serialReferenceMedia($context['revision']);

    get(route('characters.references.show', ['revision' => $context['revision'], 'media' => $media]))->assertRedirect(route('login'));
});

test('референс скрыт под другой версией', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $media = serialReferenceMedia($context['revision']);
    $other = CharacterRevision::factory()->create();

    actingAs(serialOperator())
        ->get(route('characters.references.show', ['revision' => $other, 'media' => $media]))
        ->assertNotFound();
});

test('подтверждённый посторонний пользователь не читает приватный референс', function (): void {
    Storage::fake('local');
    $context = serialCharacterContext();
    $media = serialReferenceMedia($context['revision']);
    config(['services.admin.email' => 'operator@example.test', 'horizon.allowed_emails' => []]);

    actingAs(User::factory()->create(['email' => 'reader@example.test']))
        ->get(route('characters.references.show', ['revision' => $context['revision'], 'media' => $media]))
        ->assertForbidden();
});
