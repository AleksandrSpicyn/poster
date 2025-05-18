<?php

namespace App\Tasks;

use App\Models\Prompt;
use App\Settings\GeneralSettings;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class BuildVideo extends Task
{
    public function __construct(private readonly GeneralSettings $settings)
    {
    }

    /** TODO Watermark */
    public function run(Prompt $prompt): string
    {
        $outputFile = 'final/short_' . Str::uuid() . '.mp4';

        $format = (new X264())
            ->setAudioCodec('aac')
            ->setAdditionalParameters(['-t', $this->settings->duration]);

        FFMpeg::openUrl([$prompt->video_url, $prompt->audio_url])
            ->export()
            ->addFormatOutputMapping(
                $format,
                Media::make('public', $outputFile),
                ['0:v', '1:a'],
            )
            ->save($outputFile);

        return Storage::disk('public')->path($outputFile);
    }
}
