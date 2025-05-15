<?php

namespace App\Classes;

use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/* TODO Пересадить на Interface и Abstract */
class Runway
{
    private string $baseUrl;
    private string $token;
    private string $version;

    public function __construct()
    {
        $this->baseUrl = config('services.runway.api_url');
        $this->token = config('services.runway.api_key');
        $this->version = config('services.runway.version');
    }

    /**
     * @throws ConnectionException
     */
    public function generate(string $imageUrl, string $prompt): string
    {
        return $this
            ->request('image_to_video', parameters: [
                'model' => 'gen4_turbo',
                'promptImage' => $imageUrl,
                'promptText' => $prompt,
                'ratio' => '720:1280',
                'duration' => 5,
            ])
            ->json('id');
    }

    /**
     * @throws ConnectionException
     */
    public function check(string $id): array
    {
        $response = $this->request("tasks/$id", 'GET');

        Log::info($response->json());

        return $response->json();
    }

    /**
     * @throws ConnectionException
     */
    private function request(string $url, string $method = 'POST', array $parameters = []): Response
    {
        $response = Http
            ::withToken($this->token)
            ->withHeaders([
                'X-Runway-Version' => $this->version,
                'Content-Type' => 'application/json',
            ])
            ->send($method, "$this->baseUrl/$url", [
                RequestOptions::JSON => $parameters,
            ]);

        if ($response->failed()) {
            Log::error($response->json());
            throw new RuntimeException('RunwayML API Error: ' . $response->body());
        }

        return $response;
    }
}
