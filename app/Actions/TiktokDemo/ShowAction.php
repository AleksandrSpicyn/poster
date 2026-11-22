<?php

declare(strict_types=1);

namespace App\Actions\TiktokDemo;

use App\Classes\Tiktok\TiktokClient;
use App\Data\TiktokDemo\Profile;
use App\Tasks\TiktokDemo\CreatorSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ShowAction
{
    public function __construct(private TiktokClient $client, private Store $session, private CreatorSettings $settings) {}

    /** @return array<string,mixed> */
    public function run(string $author, int $cursor): array
    {
        $key = "tiktok_demo.$author";
        $profile = Profile::fromSession($this->session, $author);
        $page = ['author' => $author, 'name' => $profile?->name, 'videos' => [], 'error' => null, 'next' => null,
            'creator' => null, 'canRefreshDirect' => false, 'directMessage' => null, 'form' => null, 'notice' => $this->session->get("$key.notice"), 'operation' => $this->session->get("$key.operation"),
            'upload' => config('tiktok_demo.upload') && is_file(storage_path('app/private/tiktok-demo/sample.mp4'))];

        if (! $profile instanceof Profile) {
            return $page;
        }

        if ($profile->expired()) {
            return [...$page, 'upload' => false, 'error' => 'Срок доступа истёк. Подключите TikTok повторно.'];
        }

        $direct = "$key.direct";
        $id = $this->session->get("$direct.id");

        $message = $this->session->get("$direct.message");

        if ($message === 'Передача не подтверждена. Проверьте результат перед повторной отправкой.' && $this->session->has("$direct.pending")) {
            $message = is_string($id)
                ? 'TikTok выдал идентификатор. Передача файла не подтверждена.'
                : 'Создание публикации не подтверждено. Идентификатор TikTok не сохранён. Причина старой попытки неизвестна.';
        }

        if (is_string($id)) {
            try {
                $reply = Http::withToken($profile->token())->withoutRedirecting()->timeout(10)
                    ->post('https://open.tiktokapis.com/v2/post/publish/status/fetch/', ['publish_id' => $id]);
                $status = $this->client->data($reply, 'status');

                if (in_array($status, ['PUBLISH_COMPLETE', 'FAILED'], true)) {
                    $this->session->forget(["$direct.id", "$direct.pending"]);
                    $message = $status === 'PUBLISH_COMPLETE' ? 'TikTok подтвердил публикацию.' : 'TikTok отклонил публикацию.';
                    $this->session->put("$direct.message", $message);
                }
            } catch (ConnectionException|RuntimeException) {
                $message = (is_string($message) ? $message . ' ' : '') . 'Не удалось проверить статус. Обновите страницу позже.';
            }
        }

        $page['directMessage'] = $message;
        $page['canRefreshDirect'] = $this->session->has("$direct.id");

        if (! $this->session->has("$direct.pending")) {
            try {
                $page['creator'] = $this->settings->run($profile->token())->toArray();
                $page['form'] = $this->session->remember("$direct.form", static fn(): string => Str::random(40));
            } catch (ConnectionException|RuntimeException) {
                $page['directMessage'] ??= 'Direct Post недоступен. Проверьте разрешение video.publish и попробуйте позже.';
            }
        }

        try {
            $response = Http::withToken($profile->token())->withoutRedirecting()->timeout(20)
                ->post(
                    'https://open.tiktokapis.com/v2/video/list/?fields=id,title,view_count,like_count,comment_count,share_count',
                    array_filter(['max_count' => 20, 'cursor' => $cursor]),
                );
            $videos = $this->client->data($response, 'videos');

            if (! is_array($videos) || ! array_is_list($videos)) {
                throw new RuntimeException('Invalid list');
            }

            return [...$page, 'videos' => $videos, 'next' => $response->json('data.has_more') === true ? $response->json('data.cursor') : null];
        } catch (ConnectionException|RuntimeException) {
            return [...$page, 'error' => 'Не удалось загрузить ролики из TikTok. Попробуйте обновить страницу.'];
        }
    }
}
