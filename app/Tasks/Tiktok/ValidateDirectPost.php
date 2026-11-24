<?php

declare(strict_types=1);

namespace App\Tasks\Tiktok;

use App\Data\Tiktok\CreatorSettingsData;
use App\Data\Tiktok\TiktokPublicationData;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

final readonly class ValidateDirectPost
{
    public function run(TiktokPublicationData $post, CreatorSettingsData $creator, string $path): void
    {
        if (! in_array($post->privacy, $creator->privacy, true)
            || ($post->comment && ! $creator->comment) || ($post->duet && ! $creator->duet) || ($post->stitch && ! $creator->stitch)) {
            throw ValidationException::withMessages(['privacy' => 'Настройки аккаунта изменились. Выберите доступные параметры.']);
        }

        if ($post->disclose !== ($post->own_brand || $post->partner)
            || ($post->partner && ! in_array($post->privacy, ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS'], true))) {
            throw ValidationException::withMessages(['disclose' => 'Проверьте тип рекламы и видимость ролика.']);
        }

        if (strlen(mb_convert_encoding($post->title ?? '', 'UTF-16LE', 'UTF-8')) > 4400) {
            throw ValidationException::withMessages(['title' => 'Подпись превышает лимит TikTok в 2200 единиц UTF-16.']);
        }

        $probe = Process::timeout(8)->run([
            'ffprobe',
            '-v',
            'error',
            '-protocol_whitelist',
            'file,pipe',
            '-f',
            'mov',
            '-i',
            $path,
            '-select_streams',
            'v:0',
            '-show_entries',
            'format=duration:stream=codec_type',
            '-of',
            'json',
        ]);
        $info = json_decode($probe->output(), true);
        $duration = data_get($info, 'format.duration');

        if (! $probe->successful() || data_get($info, 'streams.0.codec_type') !== 'video' || ! is_numeric($duration) || (float) $duration <= 0 || (float) $duration > $creator->duration) {
            throw ValidationException::withMessages(['video' => 'Выберите MP4 с видео в пределах лимита длительности аккаунта.']);
        }
    }
}
