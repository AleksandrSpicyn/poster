<?php

declare(strict_types=1);

namespace App\Tasks\TiktokDemo;

use App\Classes\Tiktok\TiktokClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class UploadSample
{
    public function __construct(private TiktokClient $client) {}

    public function run(string $access): void
    {
        $path = storage_path('app/private/tiktok-demo/sample.mp4');
        abort_unless(is_file($path) && File::size($path) > 0 && File::size($path) <= 10 * 1024 * 1024, 422, 'Нужен тестовый MP4 до 10 МиБ.');
        $bytes = File::get($path);
        $size = strlen($bytes);
        $response = Http::withToken($access)->withoutRedirecting()->timeout(20)->post('https://open.tiktokapis.com/v2/post/publish/inbox/video/init/', [
            'source_info' => ['source' => 'FILE_UPLOAD', 'video_size' => $size, 'chunk_size' => $size, 'total_chunk_count' => 1],
        ]);
        $url = $this->client->data($response, 'upload_url');

        if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || ! str_ends_with((string) parse_url($url, PHP_URL_HOST), '.tiktokapis.com')) {
            throw new RuntimeException('Invalid upload destination');
        }

        $transfer = Http::withoutRedirecting()->timeout(45)->withBody($bytes, 'video/mp4')
            ->withHeaders(['Content-Range' => sprintf('bytes 0-%d/%d', $size - 1, $size)])->put($url);

        if ($transfer->status() !== 201) {
            throw new RuntimeException('Transfer not confirmed');
        }
    }
}
