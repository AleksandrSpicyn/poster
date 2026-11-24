<?php

declare(strict_types=1);

namespace App\Classes\Billing;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class MeteredVideo
{
    public static function assertMatchesQuote(string $path, ?int $seconds): void
    {
        if ($seconds === null) {
            return;
        }

        $probe = Process::timeout(30)->run([
            'ffprobe',
            '-v',
            'error',
            '-protocol_whitelist',
            'file,pipe',
            '-i',
            $path,
            '-show_entries',
            'stream=codec_type,width,height,duration:format=duration',
            '-of',
            'json',
        ])->throw();
        /** @var array{streams?: list<array{codec_type: string, width?: int, height?: int, duration?: string}>, format?: array{duration?: string}} $metadata */
        $metadata = json_decode($probe->output(), true, flags: JSON_THROW_ON_ERROR);
        $streams = collect($metadata['streams'] ?? []);
        $video = $streams->firstWhere('codec_type', 'video');
        $duration = $video['duration'] ?? $metadata['format']['duration'] ?? null;

        if (($video['width'] ?? null) !== 720 || ($video['height'] ?? null) !== 1280 || ! is_numeric($duration)
            || abs((float) $duration - $seconds) > 0.5 || ! $streams->contains('codec_type', 'audio')) {
            throw new RuntimeException('Параметры файла расходятся с резервом. Требуется проверка исходного результата.');
        }
    }
}
