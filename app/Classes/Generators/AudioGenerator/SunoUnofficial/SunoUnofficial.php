<?php

declare(strict_types=1);

namespace App\Classes\Generators\AudioGenerator\SunoUnofficial;

use App\Classes\Generators\AudioGenerator\AudioGenerator;
use App\Support\Integrations\Payload;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

readonly class SunoUnofficial implements AudioGenerator
{
    public function __construct(
        #[Config('services.suno_unofficial.api_url')] private string $baseUrl,
        #[Config('services.suno_unofficial.api_key')] private string $token,
    ) {}

    /** @throws ConnectionException */
    public function generate(string $title, string $prompt): string
    {
        $request = $this
            ->request([
                'prompt' => $prompt,
                'style' => 'epic cinematic',
                'title' => Str::limit($title, 50),
                'customMode' => true,
                'instrumental' => true,
                'model' => 'V3_5',
                'negativeTags' => ['Classical'],
                'callBackUrl' => route('webhook.suno'),
            ]);

        return Payload::string($request->json('data.taskId'), 'data.taskId');
    }

    /**
     * @param array<string, mixed> $parameters
     * @throws ConnectionException
     */
    private function request(array $parameters): Response
    {
        $response = Http::withToken($this->token)
            ->connectTimeout(10)
            ->timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post("$this->baseUrl/generate", $parameters);

        if ($response->failed()) {
            Log::error('SunoUnofficial API Error', ['status' => $response->status()]);

            throw new RuntimeException('SunoUnofficial API request failed.');
        }

        return $response;
    }
}
