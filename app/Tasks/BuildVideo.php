<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Models\Prompt;
use App\Settings\GeneralSettings;
use Closure;
use FFMpeg\Format\Video\X264;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

/**
 * Собирает ролик из видео- и аудиоисточников независимо от их провайдера.
 * Временный файл доступен получателю до завершения callback.
 */
readonly class BuildVideo
{
    public function __construct(
        private GeneralSettings $settings,
        #[Storage('local')] private FilesystemAdapter $disk,
    ) {}

    /** @param Closure(string): void $attach */
    public function run(Prompt $prompt, Closure $attach): void
    {
        $outputFile = 'final/short_' . Str::uuid() . '.mp4';

        $format = new X264();
        $format->setAudioCodec('aac');

        $duration = data_get($prompt->input_snapshot, 'video_request.duration');
        $format->setAdditionalParameters(['-t', (string) (is_int($duration) && $duration > 0 ? $duration : $this->settings->duration)]);

        $exporter = FFMpeg::openUrl([$prompt->video_url, $prompt->audio_url])->export();
        $exporter->addFormatOutputMapping(
            $format,
            Media::make('local', $outputFile),
            ['0:v', '1:a'],
        );

        try {
            $exporter->save($outputFile);
            $attach($this->disk->path($outputFile));
        } finally {
            $this->disk->delete($outputFile);
        }
    }
}
