<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\BuiltEpisodeVideoData;
use App\Data\Episodes\ContinuationMediaData;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ExtractContinuationMedia
{
    public function run(BuiltEpisodeVideoData $video): ContinuationMediaData
    {
        $frame = $video->workspace . '/last-frame.png';
        $tail = $video->workspace . '/last-tail.mp4';
        $frameStart = number_format(max(0, $video->durationMs - 500) / 1000, 3, '.', '');
        $tailStart = number_format(max(0, $video->durationMs - 2000) / 1000, 3, '.', '');
        $result = Process::timeout(120)->run([
            'ffmpeg',
            '-v',
            'error',
            '-nostdin',
            '-y',
            '-ss',
            $frameStart,
            '-i',
            $video->path,
            '-an',
            '-update',
            '1',
            '-f',
            'image2',
            $frame,
        ]);

        if (! $result->successful() || ! is_file($frame) || @getimagesize($frame) === false) {
            throw new RuntimeException('The final continuation frame could not be extracted.');
        }

        $result = Process::timeout(120)->run([
            'ffmpeg',
            '-v',
            'error',
            '-nostdin',
            '-y',
            '-ss',
            $tailStart,
            '-i',
            $video->path,
            '-c:v',
            'libx264',
            '-preset',
            'veryfast',
            '-threads',
            '2',
            '-pix_fmt',
            'yuv420p',
            '-c:a',
            'aac',
            '-movflags',
            '+faststart',
            $tail,
        ]);

        if (! $result->successful() || ! is_file($tail) || filesize($tail) < 1) {
            throw new RuntimeException('The final continuation fragment could not be extracted.');
        }

        return new ContinuationMediaData($frame, $tail, max(0, $video->durationMs - 42));
    }
}
