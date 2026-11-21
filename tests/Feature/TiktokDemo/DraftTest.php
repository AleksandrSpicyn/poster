<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

test('чужой адрес и отключённая отправка блокируются до загрузки', function (): void {
    authenticateDemoAuthor();
    withSession(['tiktok_demo.author-b.profile' => demoProfile('Bravo')]);

    post(route('tiktok-demo.draft', 'author-a'))->assertForbidden();
    config(['tiktok_demo.upload' => true]);
    post(route('tiktok-demo.draft', 'author-b'))->assertNotFound();
    post(route('tiktok-demo.draft', 'author-a'), ['author' => 'author-b'])->assertForbidden();

    Http::assertNothingSent();
});

test('передаёт один тестовый файл во входящие и блокирует повтор', function (): void {
    authenticateDemoAuthor();
    withSession(['tiktok_demo.author-a.profile' => demoProfile('Alpha')]);
    config(['tiktok_demo.upload' => true]);
    $storage = storage_path();
    $temp = sys_get_temp_dir() . '/poster-demo-' . Str::uuid();
    app()->useStoragePath($temp);
    File::ensureDirectoryExists(storage_path('app/private/tiktok-demo'));
    File::put(storage_path('app/private/tiktok-demo/sample.mp4'), 'test');
    $url = 'https://open-upload.tiktokapis.com/video/?dummy=test';
    Http::fake(['*/inbox/video/init/' => Http::response(['error' => ['code' => 'ok'], 'data' => ['upload_url' => $url]]), $url => Http::response('', 201)]);

    try {
        post(route('tiktok-demo.draft', 'author-a'))->assertRedirect()->assertSessionHas('tiktok_demo.author-a.operation')->assertSessionMissing('tiktok_demo.author-b.operation');
        post(route('tiktok-demo.draft', 'author-a'))->assertConflict();

        Http::assertSentCount(2);
        Http::assertSent(fn(Request $request): bool => $request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer test-access-Alpha') && $request['source_info'] === ['source' => 'FILE_UPLOAD', 'video_size' => 4, 'chunk_size' => 4, 'total_chunk_count' => 1]);
        Http::assertSent(fn(Request $request): bool => $request->method() === 'PUT' && ! $request->hasHeader('Authorization') && $request->hasHeader('Content-Range', 'bytes 0-3/4') && $request->body() === 'test');
    } finally {
        app()->useStoragePath($storage);
        File::deleteDirectory($temp);
    }
});
