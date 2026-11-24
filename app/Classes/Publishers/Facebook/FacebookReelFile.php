<?php

declare(strict_types=1);

namespace App\Classes\Publishers\Facebook;

use App\Models\EpisodeAssembly;
use App\Models\VideoUpscaleOperation;
use App\Repositories\MediaRepository;
use Illuminate\Support\Facades\Process;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class FacebookReelFile
{
    public function __construct(private MediaRepository $media) {}

    public function exportError(EpisodeAssembly $assembly, ?VideoUpscaleOperation $export): ?string
    {
        $file = $export?->result_media_id === null || $export->result_sha256 === null ? null
            : $this->media->episodeExport($assembly, $export->result_media_id, $export->result_sha256);

        return $file instanceof Media ? self::error($file->getPath()) : 'Подготовьте экспорт Full HD для проверки Facebook Reels.';
    }

    public static function error(string $path): ?string
    {
        $probe = Process::timeout(15)->run(['ffprobe', '-v', 'error', '-protocol_whitelist', 'file,pipe', '-f', 'mov', '-show_streams', '-show_format', '-of', 'json', $path]);
        /** @var array{format?: array{duration?: string}, streams?: list<array{codec_type: string, codec_name: string, width?: int, height?: int, channels?: int, sample_rate?: string, bit_rate?: string, profile?: string, avg_frame_rate?: string}>}|null $info */
        $info = json_decode($probe->output(), true);
        $streams = collect($info['streams'] ?? []);
        $visual = $streams->firstWhere('codec_type', 'video');
        $audio = $streams->firstWhere('codec_type', 'audio');
        $rate = explode('/', $visual['avg_frame_rate'] ?? '');
        $fps = (float) $rate[0] / max(1, (float) ($rate[1] ?? 1));
        $duration = (float) ($info['format']['duration'] ?? 0);

        if (! $probe->successful() || ($visual['width'] ?? 0) < 540 || ($visual['height'] ?? 0) * 9 !== $visual['width'] * 16
            || $fps < 24 || $fps > 60 || $duration < 3 || $duration > 90 || ! in_array($visual['codec_name'], ['h264', 'hevc', 'vp9', 'av1'], true)
            || ($audio !== null && ($audio['codec_name'] !== 'aac' || ($audio['profile'] ?? '') !== 'LC' || (int) ($audio['bit_rate'] ?? 0) < 128000 || ($audio['channels'] ?? 0) !== 2 || (int) ($audio['sample_rate'] ?? 0) !== 48000))) {
            return 'Facebook требует вертикальный MP4 9:16 от 540×960, 24–60 кадров/с, длительностью 3–90 секунд и совместимый звук.';
        }

        return null;
    }
}
