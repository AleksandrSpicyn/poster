<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Support\Integrations\Payload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DownloadShotVideo
{
    public function run(GenerationOperation $operation): Media
    {
        $prompt = $operation->prompt()->firstOrFail();
        $existing = $prompt->getFirstMedia(Prompt::SHOT_VIDEO);

        if ($existing !== null) {
            return $existing;
        }

        $url = Payload::string($operation->result_snapshot['video_url'] ?? null, 'video_url');
        $host = parse_url($url, PHP_URL_HOST);

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! is_string($host) || parse_url($url, PHP_URL_USER) !== null
            || $host === 'localhost' || str_ends_with($host, '.localhost')
            || (filter_var($host, FILTER_VALIDATE_IP) !== false && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
            throw new RuntimeException('The provider returned an unsafe download URL.');
        }

        $path = Storage::disk('local')->path('shot-work/' . Str::uuid() . '.mp4');
        File::ensureDirectoryExists(dirname($path), 0700);

        try {
            Http::connectTimeout(10)->timeout(180)->withoutRedirecting()->withOptions([
                'progress' => static function (int|float $total, int|float $downloaded): void {
                    if ($total > 200_000_000 || $downloaded > 200_000_000) {
                        throw new RuntimeException('The generated video exceeds 200 MB.');
                    }
                },
            ])->sink($path)->get($url)->throw();

            if (! File::isFile($path) || File::size($path) < 1 || File::size($path) > 200_000_000) {
                throw new RuntimeException('The generated video is empty or exceeds 200 MB.');
            }

            $probe = Process::timeout(30)->run(['ffprobe', '-v', 'error', '-protocol_whitelist', 'file,pipe', '-f', 'mov', '-i', $path, '-select_streams', 'v:0', '-show_entries', 'stream=codec_type', '-of', 'csv=p=0']);
            $probe->throw();

            if (trim($probe->output()) !== 'video') {
                throw new RuntimeException('The generated file has no readable video stream.');
            }

            return $prompt->addMedia($path)->toMediaCollection(Prompt::SHOT_VIDEO, 'local');
        } finally {
            if (File::isFile($path)) {
                File::delete($path);
            }
        }
    }
}
