<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;

use function Pest\Laravel\post;

test('открывает Login Kit с отдельным ключом и едиными правами автора', function (bool $upload, string $expected): void {
    authenticateDemoAuthor();
    config(['tiktok_demo.upload' => $upload]);
    post(route('tiktok-demo.connect', 'author-b'))->assertNotFound();

    $response = post(route('tiktok-demo.connect', 'author-a'))->assertRedirect();
    $url = $response->headers->get('Location');
    Assert::assertIsString($url);
    $parameters = parse_url($url, PHP_URL_QUERY);
    Assert::assertIsString($parameters);
    parse_str($parameters, $query);
    Assert::assertIsString($query['state'] ?? null);

    expect(parse_url($url, PHP_URL_HOST))->toBe('www.tiktok.com')
        ->and($query['client_key'])->toBe('sandbox-key')
        ->and($query['scope'])->toBe($expected)
        ->and($query['redirect_uri'])->toBe(config('tiktok_demo.redirect'))
        ->and(strlen($query['state']))->toBe(64);
    $response->assertSessionHas('tiktok_demo.state.value', $query['state']);
})->with([
    [false, 'user.info.basic,video.list,video.publish'],
    [true, 'user.info.basic,video.list,video.publish,video.upload'],
]);
