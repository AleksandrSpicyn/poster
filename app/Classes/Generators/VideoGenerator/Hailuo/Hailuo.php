<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Hailuo;

use App\Classes\Generators\VideoGenerator\AlternativeVideoGenerator;
use App\Exceptions\VideoGeneratingException;
use App\Settings\GeneralSettings;
use App\Support\Integrations\Payload;
use GuzzleHttp\RequestOptions;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

readonly class Hailuo implements AlternativeVideoGenerator
{
    public function __construct(
        #[Config('services.hailuo.api_url')] private string $baseUrl,
        #[Config('services.hailuo.api_key')] private string $token,
        #[Config('services.hailuo.group_id')] private string $groupId,
        private GeneralSettings $settings,
    ) {}

    /**
     * @throws ConnectionException
     */
    public function generate(string $imageUrl, string $prompt): string
    {
        $response = $this
            ->request('video_generation', parameters: [
                'model' => 'MiniMax-Hailuo-02',
                'first_frame_image' => $imageUrl,
                'prompt' => $prompt,
                'duration' => $this->settings->duration,
            ])
            ->json('task_id');

        return Payload::string($response, 'task_id');
    }

    /**
     * @throws VideoGeneratingException
     * @throws ConnectionException
     */
    public function check(string $id): ?string
    {
        $fileId = $this
            ->request("query/video_generation/?task_id=$id", 'GET')
            ->json('file_id');

        if (! $fileId) {
            return null;
        }

        $url = $this->get(Payload::string($fileId, 'file_id'));

        if (! $url) {
            Log::error('Video url generation failed:', [
                'fileId' => $fileId,
            ]);

            throw new VideoGeneratingException();
        }

        return $url;
    }

    /**
     * @throws ConnectionException
     */
    public function get(string $fileId): ?string
    {
        $url = $this->request("files/retrieve?GroupId=$this->groupId&file_id=$fileId", 'GET')->json('file.download_url');

        return $url === null ? null : Payload::string($url, 'file.download_url');
    }

    /**
     * @param array<string, mixed> $parameters
     * @throws ConnectionException
     */
    private function request(string $url, string $method = 'POST', array $parameters = []): Response
    {
        $response = Http::withToken($this->token)
            ->connectTimeout(10)
            ->timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->send(
                $method,
                "$this->baseUrl/$url",
                $parameters !== []
                    ? [RequestOptions::JSON => $parameters]
                    : [],
            );

        if ($response->failed()) {
            Log::error('Hailuo API Error', ['status' => $response->status()]);

            throw new RuntimeException('Hailuo API request failed.');
        }

        return $response;
    }
}
