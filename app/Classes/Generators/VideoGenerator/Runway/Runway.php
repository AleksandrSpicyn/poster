<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Runway;

use App\Classes\Generators\VideoGenerator\AlternativeVideoGenerator;
use App\Classes\Generators\VideoGenerator\Runway\Data\CreationData;
use App\Classes\Generators\VideoGenerator\Runway\Enums\GenerationStatus;
use App\Enums\ContentType;
use App\Enums\HttpMethod;
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

/**
 * Резервный адаптер Runway: переводит статусы задачи в URL, ожидание (null)
 * или VideoGeneratingException, с которой polling job завершается ошибкой.
 */
readonly class Runway implements AlternativeVideoGenerator
{
    public function __construct(
        #[Config('services.runway.api_url')] private string $baseUrl,
        #[Config('services.runway.api_key')] private string $token,
        #[Config('services.runway.version')] private string $version,
        private GeneralSettings $settings,
    ) {}

    /**
     * @throws ConnectionException
     */
    public function generate(string $imageUrl, string $prompt): string
    {
        $id = $this
            ->request('image_to_video', parameters: [
                'model' => 'gen4_turbo',
                'promptImage' => $imageUrl,
                'promptText' => $prompt,
                'ratio' => '720:1280',
                'duration' => $this->settings->duration,
            ])
            ->json('id');

        return Payload::string($id, 'id');
    }

    /**
     * @throws ConnectionException
     * @throws VideoGeneratingException
     */
    public function check(string $id): ?string
    {
        $response = $this->request("tasks/$id", HttpMethod::GET);

        $dto = CreationData::from($response->json());

        if ($dto->status === GenerationStatus::FAILED->value) {
            Log::error("Video generation failed: $dto->failure", [
                'id' => $dto->id,
                'createdAt' => $dto->createdAt,
            ]);

            throw new VideoGeneratingException();
        }

        return $dto->status === GenerationStatus::SUCCEEDED->value
            ? ($dto->output[0] ?? throw new VideoGeneratingException('Completed video has no output.'))
            : null;
    }

    /**
     * @param array<string, mixed> $parameters
     * @throws ConnectionException
     */
    private function request(string $url, HttpMethod $method = HttpMethod::POST, array $parameters = []): Response
    {
        $response = Http::withToken($this->token)
            ->connectTimeout(10)
            ->timeout(60)
            ->withHeaders([
                'X-Runway-Version' => $this->version,
                'Content-Type' => ContentType::JSON->value,
            ])
            ->send($method->value, "$this->baseUrl/$url", [
                RequestOptions::JSON => $parameters,
            ]);

        if ($response->failed()) {
            Log::error('Runway API Error', ['status' => $response->status()]);

            throw new RuntimeException('Runway API request failed.');
        }

        return $response;
    }
}
