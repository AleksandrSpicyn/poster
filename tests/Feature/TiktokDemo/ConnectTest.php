<?php

declare(strict_types=1);

use function Pest\Laravel\post;

test('открывает Login Kit с отдельным ключом и едиными правами автора', function (): void {
    authenticateDemoAuthor();
    post(route('tiktok-demo.connect', 'author-b'))->assertNotFound();

    $response = post(route('tiktok-demo.connect', 'author-a'))->assertRedirect();
    $url = $response->headers->get('Location');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect(parse_url($url, PHP_URL_HOST))->toBe('www.tiktok.com')
        ->and($query['client_key'])->toBe('sandbox-key')
        ->and($query['scope'])->toBe('user.info.basic,video.list,video.upload')
        ->and($query['redirect_uri'])->toBe(config('tiktok_demo.redirect'))
        ->and(strlen($query['state']))->toBe(64);
    $response->assertSessionHas('tiktok_demo.state.value', $query['state']);
});
