<?php

declare(strict_types=1);

namespace App\Actions\TiktokDemo;

use App\Classes\Tiktok\TiktokClient;
use App\Classes\Tiktok\TransferVideo;
use App\Data\TiktokDemo\DirectPostData;
use App\Data\TiktokDemo\Profile;
use App\Support\Integrations\Payload;
use App\Tasks\TiktokDemo\CreatorSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class DirectPostAction
{
    public function __construct(private Store $session, private CreatorSettings $settings, private TiktokClient $client, private TransferVideo $transfer) {}

    public function run(string $author, DirectPostData $post): void
    {
        $key = "tiktok_demo.$author.direct";
        $profile = Profile::fromSession($this->session, $author);
        abort_unless($profile instanceof Profile && ! $profile->expired(), 403);
        abort_unless($this->session->get("$key.form") === $post->binding && ! $this->session->has("$key.pending"), 409);

        try {
            $creator = $this->settings->run($profile->token());
        } catch (ConnectionException|RuntimeException) {
            throw ValidationException::withMessages(['privacy' => 'TikTok сейчас не разрешает публикацию. Обновите страницу позже.']);
        }

        if (! in_array($post->privacy, $creator['privacy'], true)
            || ($post->comment && ! $creator['comment']) || ($post->duet && ! $creator['duet']) || ($post->stitch && ! $creator['stitch'])) {
            throw ValidationException::withMessages(['privacy' => 'Настройки аккаунта изменились. Выберите доступные параметры.']);
        }

        if ($post->disclose !== ($post->own_brand || $post->partner)
            || ($post->partner && ! in_array($post->privacy, ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS'], true))) {
            throw ValidationException::withMessages(['disclose' => 'Проверьте тип рекламы и видимость ролика.']);
        }

        if (strlen(mb_convert_encoding($post->title ?? '', 'UTF-16LE', 'UTF-8')) > 4400) {
            throw ValidationException::withMessages(['title' => 'Подпись превышает лимит TikTok в 2200 единиц UTF-16.']);
        }

        $probe = Process::timeout(8)->run(['ffprobe', '-v', 'error', '-protocol_whitelist', 'file,pipe', '-f', 'mov', '-i', $post->video->getPathname(),
            '-select_streams', 'v:0', '-show_entries', 'format=duration:stream=codec_type', '-of', 'json']);
        $info = json_decode($probe->output(), true);
        $duration = data_get($info, 'format.duration');

        if (! $probe->successful() || data_get($info, 'streams.0.codec_type') !== 'video' || ! is_numeric($duration) || (float) $duration <= 0 || (float) $duration > $creator['duration']) {
            throw ValidationException::withMessages(['video' => 'Выберите MP4 с видео в пределах лимита длительности аккаунта.']);
        }

        $bytes = $post->video->getContent();
        $size = strlen($bytes);
        $this->session->forget("$key.form");
        $this->session->put("$key.pending", true);
        $this->session->put("$key.message", 'Передача не подтверждена. Проверьте результат перед повторной отправкой.');
        $this->session->save();

        try {
            $reply = Http::withToken($profile->token())->withoutRedirecting()->timeout(12)
                ->post('https://open.tiktokapis.com/v2/post/publish/video/init/', [
                    'source_info' => ['source' => 'FILE_UPLOAD', 'video_size' => $size, 'chunk_size' => $size, 'total_chunk_count' => 1],
                    'post_info' => ['title' => $post->title ?? '', 'privacy_level' => $post->privacy, 'disable_comment' => ! $post->comment,
                        'disable_duet' => ! $post->duet, 'disable_stitch' => ! $post->stitch, 'brand_organic_toggle' => $post->own_brand,
                        'brand_content_toggle' => $post->partner, 'is_aigc' => $post->ai],
                ]);
            $id = Payload::string($this->client->data($reply, 'publish_id'), 'publish_id');
            $this->session->put("$key.id", $id);
            $this->session->save();
            $this->transfer->run($reply->json('data.upload_url'), $bytes);
            $this->session->put("$key.message", 'Файл передан. TikTok обрабатывает видео.');
        } catch (ConnectionException|RuntimeException) {
            // Keep the pending receipt: a lost response can follow a successful submission.
        }
    }
}
