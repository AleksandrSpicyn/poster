<?php

declare(strict_types=1);

namespace App\Actions\TiktokDemo;

use App\Classes\Tiktok\TiktokClient;
use App\Classes\Tiktok\TransferVideo;
use App\Data\TiktokDemo\DirectPostData;
use App\Data\TiktokDemo\Profile;
use App\Support\Integrations\Payload;
use App\Tasks\TiktokDemo\CreatorSettings;
use App\Tasks\TiktokDemo\ValidateDirectPost;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class DirectPostAction
{
    public function __construct(private Store $session, private CreatorSettings $settings, private ValidateDirectPost $validate, private TiktokClient $client, private TransferVideo $transfer) {}

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

        $this->validate->run($post, $creator);

        $bytes = $post->video->getContent();
        $size = strlen($bytes);
        $this->session->forget("$key.form");
        $this->session->put("$key.pending", true);
        $this->session->put("$key.message", 'Создание публикации не подтверждено. Poster ещё не получил идентификатор TikTok.');
        $this->session->save();

        try {
            $reply = Http::withToken($profile->token())->withoutRedirecting()->timeout(12)
                ->post('https://open.tiktokapis.com/v2/post/publish/video/init/', [
                    'source_info' => ['source' => 'FILE_UPLOAD', 'video_size' => $size, 'chunk_size' => $size, 'total_chunk_count' => 1],
                    'post_info' => ['title' => $post->title ?? '', 'privacy_level' => $post->privacy, 'disable_comment' => ! $post->comment,
                        'disable_duet' => ! $post->duet, 'disable_stitch' => ! $post->stitch, 'brand_organic_toggle' => $post->own_brand,
                        'brand_content_toggle' => $post->partner, 'is_aigc' => $post->ai],
                ]);
        } catch (ConnectionException) {
            $this->session->put("$key.message", 'Создание публикации не подтверждено. Ответ TikTok не получен.');

            return;
        }

        try {
            $id = Payload::string($this->client->data($reply, 'publish_id'), 'publish_id');
        } catch (RuntimeException) {
            $code = $reply->json('error.code');
            $detail = is_string($code) && $code !== 'ok' && preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $code) ? ", $code" : '';
            $this->session->put("$key.message", 'Создание публикации не подтверждено. TikTok не вернул идентификатор (HTTP ' . $reply->status() . $detail . ').');

            return;
        }

        $this->session->put("$key.id", $id);
        $this->session->put("$key.message", 'TikTok выдал идентификатор. Передача файла не подтверждена.');
        $this->session->save();

        try {
            $this->transfer->run($reply->json('data.upload_url'), $bytes);
            $this->session->put("$key.message", 'Файл передан. TikTok обрабатывает видео.');
        } catch (ConnectionException) {
            $this->session->put("$key.message", 'Передача файла не подтверждена. Соединение с TikTok прервалось.');
        } catch (RuntimeException $error) {
            $detail = $error->getCode() > 0 ? 'HTTP ' . $error->getCode() : 'некорректный адрес загрузки';
            $this->session->put("$key.message", "Передача файла не подтверждена ($detail).");
        }
    }
}
