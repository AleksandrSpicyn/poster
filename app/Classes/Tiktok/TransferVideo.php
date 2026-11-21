<?php

declare(strict_types=1);

namespace App\Classes\Tiktok;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TransferVideo
{
    public function run(mixed $url, string $bytes): void
    {
        if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || ! str_ends_with((string) parse_url($url, PHP_URL_HOST), '.tiktokapis.com')) {
            throw new RuntimeException('Invalid upload destination');
        }

        $size = strlen($bytes);
        $transfer = Http::withoutRedirecting()->timeout(45)->withBody($bytes, 'video/mp4')
            ->withHeaders(['Content-Range' => sprintf('bytes 0-%d/%d', $size - 1, $size)])->put($url);

        if ($transfer->status() !== 201) {
            throw new RuntimeException('Transfer not confirmed');
        }
    }
}
