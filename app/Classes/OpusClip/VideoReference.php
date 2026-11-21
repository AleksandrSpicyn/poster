<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Enums\ChannelType;

final class VideoReference
{
    public static function fromUrl(ChannelType $type, string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($type === ChannelType::TIKTOK && in_array($host, ['www.tiktok.com', 'tiktok.com', 'm.tiktok.com'], true)
            && preg_match('~^/@[^/]+/video/([0-9]{1,20})/?$~D', $path, $match)) {
            return $match[1];
        }

        if ($type === ChannelType::INSTAGRAM && in_array($host, ['instagram.com', 'www.instagram.com'], true)
            && preg_match('~^/(?:reels?|p)/([\w-]+)/?$~D', $path, $match)) {
            return $match[1];
        }

        if ($type !== ChannelType::YOUTUBE || ! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            return null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $id = match (true) {
            $host === 'youtu.be' => trim($path, '/'),
            $path === '/watch' => $params['v'] ?? null,
            str_starts_with($path, '/shorts/') => trim(substr($path, 8), '/'),
            default => null,
        };

        return is_string($id) && preg_match('/^[\w-]{11}$/D', $id) ? $id : null;
    }
}
