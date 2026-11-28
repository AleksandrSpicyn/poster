<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Classes\Generators\VoiceSample;
use App\Data\Series\SeriesAssetData;
use App\Enums\SeriesAssetKind;
use App\Models\Series;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreSeriesAsset
{
    public function run(Series $series, SeriesAssetData $data): void
    {
        if ($series->archived_at !== null) {
            throw ValidationException::withMessages(['file' => 'Сериал находится в архиве.']);
        }

        $bytes = $data->file->get();

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages(['file' => 'Не удалось прочитать файл материала.']);
        }

        if ($data->kind === SeriesAssetKind::SEASON) {
            $plan = json_decode($bytes, true);

            if (! is_array($plan) || ($plan['format'] ?? null) !== 'poster.season.v1' || ! is_array($plan['episodes'] ?? null)
                || ! array_is_list($plan['episodes']) || count($plan['episodes']) < 1 || count($plan['episodes']) > 100) {
                throw ValidationException::withMessages(['file' => 'Ожидается JSON сезона в формате poster.season.v1 с 1–100 эпизодами.']);
            }

            $extension = 'json';
        } elseif ($data->kind === SeriesAssetKind::SOUND) {
            $bytes = VoiceSample::fromUpload($bytes, $data->file->getMimeType(), 10)->wav;
            $extension = 'wav';
        } elseif ($data->kind === SeriesAssetKind::INSERT && in_array($mime = $data->file->getMimeType(), ['video/mp4', 'video/webm'], true)) {
            $extension = $mime === 'video/mp4' ? 'mp4' : 'webm';
            $stream = Process::timeout(15)->run([
                'ffprobe', '-v', 'error', '-protocol_whitelist', 'file', '-f', $extension === 'mp4' ? 'mov' : 'matroska',
                '-select_streams', 'V:0', '-show_entries', 'stream=width,height', '-of', 'csv=p=0', $data->file->getPathname(),
            ]);

            if (! $stream->successful() || preg_match('/^[1-9][0-9]*,[1-9][0-9]*$/D', trim($stream->output())) !== 1) {
                throw ValidationException::withMessages(['file' => 'Нужен читаемый файл MP4 или WebM с видеодорожкой.']);
            }
        } else {
            $info = @getimagesizefromstring($bytes);

            if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)
                || min($info[0], $info[1]) < 64 || max($info[0], $info[1]) > 6000 || $info[0] * $info[1] > 20000000) {
                throw ValidationException::withMessages(['file' => 'Загрузите PNG, JPEG или WebP размером 64–6000 пикселей, до 20 Мп.']);
            }

            $extension = image_type_to_extension($info[2], false);
        }

        $series->addMediaFromString($bytes)->usingName($data->label)->usingFileName(Str::uuid() . '.' . $extension)
            ->withCustomProperties(['kind' => $data->kind->value, 'checksum' => hash('sha256', $bytes)])->toMediaCollection(Series::ASSETS);
    }
}
