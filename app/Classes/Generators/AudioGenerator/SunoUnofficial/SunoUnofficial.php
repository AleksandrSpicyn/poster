<?php

namespace App\Classes\Generators\AudioGenerator\SunoUnofficial;

use App\Classes\Generators\AudioGenerator\AbstractAudioGenerator;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class SunoUnofficial extends AbstractAudioGenerator
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.suno_unofficial.api_url');
        $this->token = config('services.suno_unofficial.api_key');
    }

    public function generate(string $title, string $prompt): string
    {
        $request = $this
            ->request('generate', parameters: [
                'prompt' => $prompt,
                'style' => 'epic cinematic',
                'title' => Str::limit($title, 50),
                'customMode' => true,
                'instrumental' => true,
                'model' => 'V3_5',
                'negativeTags' => ['Classical'],
                'callBackUrl' => route('webhook.suno'),
            ]);

        return $request->json('data.taskId');
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
            Log::error('SunoUnofficial API Error', $response->json());

            throw new RuntimeException('SunoUnofficial API Error: ' . $response->body());
        }

        return $response;
    }
}
