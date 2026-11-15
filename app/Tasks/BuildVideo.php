<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Models\Prompt;
use App\Settings\GeneralSettings;
use FFMpeg\Format\Video\X264;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

/**
 * Собирает видео и отдельную аудиодорожку через FFmpeg с общей длительностью
 * из настроек; возвращает локальный путь для передачи в Media Library.
 */
readonly class BuildVideo
{
    public function __construct(
        private GeneralSettings $settings,
        #[Storage('public')] private FilesystemAdapter $disk,
    ) {}

    /** TODO Watermark */
    public function run(Prompt $prompt): string
    {
        $outputFile = 'final/short_' . Str::uuid() . '.mp4';

        $format = new X264();
        $format->setAudioCodec('aac');
        $format->setAdditionalParameters(['-t', (string) $this->settings->duration]);

        $exporter = FFMpeg::openUrl([$prompt->video_url, $prompt->audio_url])->export();
        // Звук берётся только из Suno: исходная дорожка видеогенератора не входит в итоговый ролик.
        $exporter->addFormatOutputMapping(
            $format,
            Media::make('public', $outputFile),
            ['0:v', '1:a'],
        );
        $exporter->save($outputFile);

        return $this->disk->path($outputFile);
    }
}
