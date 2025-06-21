<?php

namespace App\Classes\Generators\VideoGenerator\Hailuo;

use App\Classes\Generators\VideoGenerator\AbstractVideoGenerator;
use App\Exceptions\VideoGeneratingException;
use App\Settings\GeneralSettings;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Hailuo extends AbstractVideoGenerator
{
    private string $baseUrl;
    private string $token;
    private string $groupId;

    public function __construct()
    {
        $this->baseUrl = config('services.hailuo.api_url');
        $this->token = config('services.hailuo.api_key');
        $this->groupId = config('services.hailuo.group_id');
    }

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
                'duration' => app(GeneralSettings::class)->duration,
            ]);

        dump($response->json());

        return $response->json('task_id');
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

        if (!$fileId) {
            Log::error('Video file generation failed');

            throw new VideoGeneratingException();
        }

        $url = $this->get($fileId);

        if (!$url) {
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
        return $this
            ->request("files/retrieve?GroupId=$this->groupId&file_id=$fileId")
            ->json('file.download_url');
    }

    /**
     * @throws ConnectionException
     */
    private function request(string $url, string $method = 'POST', array $parameters = []): Response
    {
        $response = Http::withToken($this->token)
            ->timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->send($method, "$this->baseUrl/$url", [
                RequestOptions::JSON => $parameters,
            ]);

        if ($response->failed()) {
            Log::error($response->json());

            throw new RuntimeException('Hailuo API Error: ' . $response->body());
        }

        return $response;
    }
}
