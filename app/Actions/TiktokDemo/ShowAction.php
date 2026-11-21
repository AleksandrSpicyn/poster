<?php

declare(strict_types=1);

namespace App\Actions\TiktokDemo;

use App\Classes\Tiktok\TiktokClient;
use App\Data\TiktokDemo\Profile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class ShowAction
{
    public function __construct(private TiktokClient $client, private Store $session) {}

    /** @return array<string,mixed> */
    public function run(string $author, int $cursor): array
    {
        $key = "tiktok_demo.$author";
        $profile = Profile::fromSession($this->session, $author);
        $page = ['author' => $author, 'name' => $profile?->name, 'videos' => [], 'error' => null, 'next' => null,
            'notice' => $this->session->get("$key.notice"), 'operation' => $this->session->get("$key.operation"),
            'upload' => config('tiktok_demo.upload') && is_file(storage_path('app/private/tiktok-demo/sample.mp4'))];

        if (! $profile instanceof Profile) {
            return $page;
        }

        if ($profile->expired()) {
            return [...$page, 'upload' => false, 'error' => 'Срок доступа истёк. Подключите TikTok повторно.'];
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
