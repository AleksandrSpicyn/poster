<?php

declare(strict_types=1);

namespace App\Classes\Billing;

use App\Classes\Generators\VideoGenerator\Seedance\Enums\Model;
use App\Classes\Generators\VideoGenerator\Seedance\Enums\Resolution;
use App\Enums\VideoProvider;
use Illuminate\Validation\ValidationException;

final class PilotShotQuote
{
    public static function assertVerticalFrame(string $bytes): void
    {
        $size = getimagesizefromstring($bytes);

        if ($size === false || $size[0] * 16 !== $size[1] * 9) {
            throw ValidationException::withMessages(['video' => 'Для оплачиваемого дубля нужен первый кадр в пропорции 9:16.']);
        }
    }

    public static function seconds(mixed $video): ?int
    {
        if (! is_array($video) || ($video['provider'] ?? null) !== VideoProvider::SEEDANCE->value
            || ($video['model'] ?? null) !== Model::V2_0_MINI->value || ($video['resolution'] ?? null) !== Resolution::HD->value
            || ($video['generate_audio'] ?? null) !== true || ! in_array($video['aspect_ratio'] ?? null, ['9:16', 'adaptive'], true)) {
            return null;
        }

        $seconds = $video['duration'] ?? null;

        return is_int($seconds) && $seconds >= 4 && $seconds <= Model::V2_0_MINI->maxDuration() ? $seconds : null;
    }
}
