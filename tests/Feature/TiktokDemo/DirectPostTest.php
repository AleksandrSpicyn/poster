<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

/** @return array<string,mixed> */
function directVideoPayload(): array
{
    return ['video' => new UploadedFile(base_path('tests/Fixtures/shot.mp4'), 'original.mp4', 'video/mp4', null, true),
        'privacy' => 'SELF_ONLY', 'title' => 'Авторский ролик', 'consent' => '1', 'binding' => 'test-form'];
}

/** @return array<string,mixed> */
function demoCreatorResponse(): array
{
    return ['error' => ['code' => 'ok'], 'data' => ['creator_nickname' => 'Alpha', 'max_video_post_duration_sec' => 60,
        'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'], 'comment_disabled' => false, 'duet_disabled' => true, 'stitch_disabled' => true]];
}

test('публикует выбранный файл, блокирует повтор и ждёт подтверждения TikTok', function (): void {
    authenticateDemoAuthor();
    withSession(['tiktok_demo.author-a.profile' => demoProfile('Alpha'), 'tiktok_demo.author-a.direct.form' => 'test-form']);
    $upload = 'https://open-upload.tiktokapis.com/video/?fake=upload';
    Http::fake(['*/creator_info/query/' => Http::response(demoCreatorResponse()), '*/post/publish/video/init/' => Http::response([
        'error' => ['code' => 'ok'], 'data' => ['publish_id' => 'test-post', 'upload_url' => $upload],
    ]), $upload => Http::response('', 201), '*/status/fetch/' => Http::response(['error' => ['code' => 'ok'], 'data' => ['status' => 'PUBLISH_COMPLETE']]),
        '*/video/list/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['videos' => []]])]);

    post(route('tiktok-demo.direct', 'author-b'), directVideoPayload())->assertNotFound();
    Http::assertNothingSent();
    post(route('tiktok-demo.direct', 'author-a'), directVideoPayload())->assertRedirect()->assertSessionHas('tiktok_demo.author-a.direct.pending', true);
    post(route('tiktok-demo.direct', 'author-a'), directVideoPayload())->assertConflict();

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/post/publish/video/init/') && $request->hasHeader('Authorization', 'Bearer test-access-Alpha')
        && data_get($request->data(), 'post_info.privacy_level') === 'SELF_ONLY' && data_get($request->data(), 'post_info.disable_comment') === true && data_get($request->data(), 'post_info.title') === 'Авторский ролик');
    Http::assertSent(fn(Request $request): bool => $request->url() === $upload && $request->body() === file_get_contents(base_path('tests/Fixtures/shot.mp4')));
    get(route('tiktok-demo.show', 'author-a'))->assertOk()->assertSee('TikTok подтвердил публикацию')->assertDontSee('value="PUBLIC_TO_EVERYONE"', false)
        ->assertSessionMissing('tiktok_demo.author-a.direct.pending');
});

test('проверяет согласие и настройки до передачи файла', function (array $change): void {
    authenticateDemoAuthor();
    withSession(['tiktok_demo.author-a.profile' => demoProfile('Alpha'), 'tiktok_demo.author-a.direct.form' => 'test-form']);
    Http::fake(['*/creator_info/query/' => Http::response(demoCreatorResponse())]);

    post(route('tiktok-demo.direct', 'author-a'), [...directVideoPayload(), ...$change])->assertSessionHasErrors();

    Http::assertNotSent(fn(Request $request): bool => str_ends_with($request->url(), '/video/init/'));
})->with([[['privacy' => 'PUBLIC_TO_EVERYONE']], [['duet' => '1']], [['disclose' => '1', 'partner' => '1']], [['consent' => '0']]]);

test('production отправляет выбранный публичный пост с маркировкой по согласию автора', function (): void {
    authenticateDemoAuthor();
    config(['tiktok_demo.mode' => 'production']);
    withSession(['tiktok_demo.mode' => 'production', 'tiktok_demo.author-a.profile' => demoProfile('Alpha'),
        'tiktok_demo.author-a.direct.form' => 'test-form']);
    Http::fake(['*/creator_info/query/' => Http::response(demoCreatorResponse()),
        '*/video/init/' => Http::response(['error' => ['code' => 'ok'], 'data' => ['publish_id' => 'post', 'upload_url' => 'https://open-upload.tiktokapis.com/video/']]),
        '*/video/' => Http::response('', 201)]);

    post(route('tiktok-demo.direct', 'author-a'), [...directVideoPayload(), 'privacy' => 'PUBLIC_TO_EVERYONE', 'comment' => '1',
        'disclose' => '1', 'partner' => '1', 'ai' => '1'])->assertRedirect()->assertSessionHas('tiktok_demo.author-a.direct.id', 'post');

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/video/init/')
        && data_get($request->data(), 'post_info.privacy_level') === 'PUBLIC_TO_EVERYONE' && data_get($request->data(), 'post_info.brand_content_toggle') === true
        && data_get($request->data(), 'post_info.is_aigc') === true && data_get($request->data(), 'post_info.disable_comment') === false);
});
