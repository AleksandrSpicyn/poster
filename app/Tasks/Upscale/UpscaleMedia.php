<?php

declare(strict_types=1);

namespace App\Tasks\Upscale;

use App\Data\Episodes\BuiltEpisodeVideoData;
use App\Enums\UpscaleExport;
use App\Models\EpisodeAssembly;
use App\Models\VideoUpscaleOperation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class UpscaleMedia
{
    private const int MAX_BYTES = 500_000_000;

    private function directory(VideoUpscaleOperation $operation): string
    {
        $directory = Storage::disk('local')->path('upscale-operations/' . $operation->id);
        File::ensureDirectoryExists($directory, 0700);

        return $directory;
    }

    public function input(EpisodeAssembly $assembly, VideoUpscaleOperation $operation, BuiltEpisodeVideoData $built): Media
    {
        $directory = $this->directory($operation);

        try {
            $input = $built->workspace . '/input.mp4';
            $audio = $built->workspace . '/audio.m4a';
            $this->convert(['-i', $built->path, '-map', '0:a:0', '-c:a', 'copy', $audio]);
            $this->convert(['-i', $built->path, '-map', '0:v:0', '-vf', 'tpad=stop_mode=clone:stop_duration=0.042,fps=24', '-frames:v',
                (string) max(1, round($operation->durationMs() / 1000 * 24)), '-c:v', 'libx264', '-threads', '2', '-movflags', '+faststart', $input]);
            File::move($audio, $directory . '/audio.m4a');

            return $this->attach($assembly, $operation, $input, EpisodeAssembly::UPSCALE_INPUT);
        } finally {
            File::deleteDirectory($built->workspace);
        }
    }

    public function output(EpisodeAssembly $assembly, VideoUpscaleOperation $operation, string $url, UpscaleExport $export): Media
    {
        $directory = $this->directory($operation);
        $download = $directory . '/download.mp4';
        $output = $directory . '/export.mp4';

        try {
            $this->download($url, $download);
            $duration = $operation->durationMs() / 1000;
            $source = $this->stream($download, 'v:0');
            $width = $export->width();
            $height = intdiv($width * 16, 9);

            if (abs($source['duration'] - $duration) > 0.1
                || abs($source['width'] - $width) > 8 || abs($source['height'] - $height) > 8) {
                throw new RuntimeException('Unexpected upscale geometry or duration.');
            }

            $this->convert(['-i', $download, '-i', $directory . '/audio.m4a', '-map', '0:v:0', '-map', '1:a:0',
                '-vf', "scale={$width}:{$height},setsar=1,fps=24", '-c:v', 'libx264', '-preset', 'veryfast', '-threads', '2',
                '-pix_fmt', 'yuv420p', '-c:a', 'copy', '-movflags', '+faststart', $output]);
            $video = $this->stream($output, 'v:0');
            $audio = $this->stream($output, 'a:0');

            if ($video['width'] !== $width || $video['height'] !== $height
                || $video['avg_frame_rate'] !== '24/1' || abs($video['duration'] - $duration) > 0.1
                || abs($audio['duration'] - $duration) > 0.1) {
                throw new RuntimeException('Export failed video or audio validation.');
            }

            return $this->attach($assembly, $operation, $output, EpisodeAssembly::UPSCALED);
        } finally {
            File::delete([$download, $output]);
        }
    }

    private function attach(EpisodeAssembly $assembly, VideoUpscaleOperation $operation, string $file, string $collection): Media
    {
        return $assembly->addMedia($file)->withCustomProperties([
            'upscale_operation_id' => $operation->id, 'checksum' => hash_file('sha256', $file),
        ])->toMediaCollection($collection, 'local');
    }

    /** @param list<string> $args */
    private function convert(array $args): void
    {
        $result = Process::timeout(1200)->run(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-protocol_whitelist', 'file,pipe', '-f', 'mov', ...$args]);

        if (! $result->successful()) {
            throw new RuntimeException('Upscale media conversion failed.');
        }
    }

    /** @return array{duration: float, width: int, height: int, avg_frame_rate: string} */
    private function stream(string $path, string $selector): array
    {
        $probe = Process::timeout(30)->run(['ffprobe', '-v', 'error', '-protocol_whitelist', 'file,pipe', '-f', 'mov',
            '-select_streams', $selector, '-show_entries', 'stream=width,height,avg_frame_rate,duration', '-of', 'json', $path]);
        $stream = data_get(json_decode($probe->output(), true), 'streams.0');

        if (! $probe->successful() || ! is_array($stream)) {
            throw new RuntimeException('Upscale media stream is missing.');
        }

        if (! is_numeric($stream['duration'] ?? null)) {
            throw new RuntimeException('Invalid stream duration.');
        }

        return [
            'duration' => (float) $stream['duration'],
            'width' => is_int($stream['width'] ?? null) ? $stream['width'] : 0,
            'height' => is_int($stream['height'] ?? null) ? $stream['height'] : 0,
            'avg_frame_rate' => is_string($stream['avg_frame_rate'] ?? null) ? $stream['avg_frame_rate'] : '',
        ];
    }

    private function download(string $url, string $path): void
    {
        $response = Http::withoutRedirecting()->connectTimeout(10)->timeout(300)->withOptions([
            'sink' => $path,
            'on_headers' => static function (ResponseInterface $response): void {
                if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                    throw new RuntimeException('Upscale result exceeds download limit.');
                }
            },
            'progress' => static function (float $total, float $received): void {
                if (max($total, $received) > self::MAX_BYTES) {
                    throw new RuntimeException('Upscale result exceeds download limit.');
                }
            },
        ])->get($url);

        if (! $response->successful() || ! is_file($path) || filesize($path) < 1 || filesize($path) > self::MAX_BYTES) {
            throw new RuntimeException('Upscale result download failed.');
        }
    }
}
