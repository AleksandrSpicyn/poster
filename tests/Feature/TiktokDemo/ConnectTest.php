<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;

use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

test('открывает Login Kit с едиными правами и принудительным согласием только в Sandbox', function (bool $upload, string $expected, string $mode, ?string $autoAuth): void {
    authenticateDemoAuthor();
    config(['tiktok_demo.upload' => $upload, 'tiktok_demo.mode' => $mode, 'tiktok_demo.key' => $mode . '-key']);
    withSession(['tiktok_demo.mode' => $mode]);
    post(route('tiktok-demo.connect', 'author-b'))->assertNotFound();

    $response = post(route('tiktok-demo.connect', 'author-a'))->assertRedirect();
    $url = $response->headers->get('Location');
    Assert::assertIsString($url);
    $parameters = parse_url($url, PHP_URL_QUERY);
    Assert::assertIsString($parameters);
    parse_str($parameters, $query);
    Assert::assertIsString($query['state'] ?? null);

    expect(parse_url($url, PHP_URL_HOST))->toBe('www.tiktok.com')
        ->and($query['client_key'])->toBe($mode . '-key')
        ->and($query['scope'])->toBe($expected)
        ->and($query['disable_auto_auth'] ?? null)->toBe($autoAuth)
        ->and($query['redirect_uri'])->toBe(config('tiktok_demo.redirect'))
        ->and(strlen($query['state']))->toBe(64);
    $response->assertSessionHas('tiktok_demo.state.value', $query['state']);
})->with([
    [false, 'user.info.basic,video.list,video.publish', 'sandbox', '1'],
    [true, 'user.info.basic,video.list,video.publish,video.upload', 'sandbox', '1'],
    [false, 'user.info.basic,video.list,video.publish', 'production', null],
]);
