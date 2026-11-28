<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

enum SeriesAssetFormat: string
{
    use EnumTrait;

    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case JSON = 'json';

    public static function of(Media $asset): ?self
    {
        return match ($asset->mime_type) {
            'image/png', 'image/jpeg', 'image/webp' => self::IMAGE,
            'video/mp4', 'video/webm' => self::VIDEO,
            'audio/wav', 'audio/x-wav', 'audio/vnd.wave', 'audio/wave' => self::AUDIO,
            default => $asset->getCustomProperty('kind') === SeriesAssetKind::SEASON->value ? self::JSON : null,
        };
    }
}
