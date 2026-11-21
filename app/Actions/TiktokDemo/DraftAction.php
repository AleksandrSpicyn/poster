<?php

declare(strict_types=1);

namespace App\Actions\TiktokDemo;

use App\Data\TiktokDemo\Profile;
use App\Tasks\TiktokDemo\UploadSample;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Session\Store;
use RuntimeException;

final readonly class DraftAction
{
    public function __construct(private UploadSample $upload, private Store $session) {}

    public function run(string $author): void
    {
        abort_unless(config()->boolean('tiktok_demo.upload'), 403);
        $key = "tiktok_demo.$author";
        $profile = Profile::fromSession($this->session, $author);
        abort_unless($profile instanceof Profile && ! $profile->expired(), 403);
        abort_if($this->session->has("$key.operation"), 409);

        try {
            $this->upload->run($profile->token());
            $this->session->put("$key.operation", 'Файл передан TikTok. После обработки откройте входящие в TikTok, чтобы завершить публикацию.');
        } catch (ConnectionException|RuntimeException) {
            $this->session->put("$key.operation", 'Передача не подтверждена. Проверьте входящие в TikTok перед повторным подключением.');
        }
    }
}
