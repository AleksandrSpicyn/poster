<?php

declare(strict_types=1);

namespace App\Classes\Publications;

use App\Enums\ChannelType;
use Illuminate\Validation\ValidationException;

final readonly class PublicationReference
{
    private function __construct(public string $value, public ?ChannelType $platform = null) {}

    public static function parse(string $input): self
    {
        if (preg_match('/\A[A-Za-z0-9_-]{1,128}\z/', $input) === 1) {
            return new self($input);
        }

        $url = parse_url($input);

        if (strlen($input) <= 2048 && is_array($url) && ($url['scheme'] ?? '') === 'https'
            && ! isset($url['user']) && ! isset($url['port']) && isset($url['host'], $url['path'])) {
            $path = $url['path'];
            $host = strtolower($url['host']);

            if (in_array($host, ['instagram.com', 'www.instagram.com'], true)
                && preg_match('~\A/(?:p|reel)/([A-Za-z0-9_-]{1,128})/?\z~', $path, $match)) {
                return new self('https://www.instagram.com/reel/' . $match[1] . '/', ChannelType::INSTAGRAM);
            }

            if (in_array($host, ['tiktok.com', 'www.tiktok.com'], true)
                && preg_match('~\A/(?:@[\w.-]+/video|player/v1)/([0-9]{1,20})/?\z~', $path, $match)) {
                return new self($match[1], ChannelType::TIKTOK);
            }

            if ($host === 'youtu.be' && preg_match('~\A/([A-Za-z0-9_-]{1,128})/?\z~', $path, $match)) {
                return new self($match[1], ChannelType::YOUTUBE);
            }

            if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
                if (preg_match('~\A/(?:shorts|live|embed)/([A-Za-z0-9_-]{1,128})/?\z~', $path, $match)) {
                    return new self($match[1], ChannelType::YOUTUBE);
                }

                parse_str($url['query'] ?? '', $query);

                if ($path === '/watch' && is_string($query['v'] ?? null) && preg_match('/\A[A-Za-z0-9_-]{1,128}\z/', $query['v'])) {
                    return new self($query['v'], ChannelType::YOUTUBE);
                }
            }
        }

        throw ValidationException::withMessages(['external_id' => 'Введите ссылку на опубликованное видео или его ID.']);
    }
}
