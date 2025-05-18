<?php

namespace App\Classes\Generators\VideoGenerator\Runway;

use App\Classes\Generators\VideoGenerator\AbstractVideoGenerator;
use App\Classes\Generators\VideoGenerator\Runway\Data\CreationData;
use App\Classes\Generators\VideoGenerator\Runway\Enums\GenerationStatus;
use App\Exceptions\VideoGeneratingException;
use App\Settings\GeneralSettings;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Runway extends AbstractVideoGenerator
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
                'duration' => app(GeneralSettings::class)->duration,
            ])
            ->json('id');
    }

    /**
     * @throws ConnectionException
     * @throws VideoGeneratingException
     */
    public function check(string $id): ?string
    {
        $response = $this->request("tasks/$id", 'GET');

        $dto = CreationData::from($response->json());

        if ($dto->status === GenerationStatus::FAILED->value) {
            Log::error("Video generation failed: $dto->failure", [
                'id' => $dto->id,
                'createdAt' => $dto->createdAt,
            ]);

            throw new VideoGeneratingException();
        }

        return $dto->status === GenerationStatus::SUCCEEDED->value
            ? Arr::first($dto->output)
            : null;
    }

    /**
     * @throws ConnectionException
     */
    private function request(string $url, string $method = 'POST', array $parameters = []): Response
    {
        $response = Http::withToken($this->token)
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
