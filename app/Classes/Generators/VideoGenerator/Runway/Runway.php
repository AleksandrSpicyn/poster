<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Runway;

use App\Classes\Generators\VideoGenerator\AsyncVideoGenerator;
use App\Classes\Generators\VideoGenerator\Runway\Data\CreationData;
use App\Classes\Generators\VideoGenerator\Runway\Enums\GenerationStatus;
use App\Data\VideoCapabilitiesData;
use App\Data\VideoGenerationRequestData;
use App\Data\VideoResultData;
use App\Enums\ContentType;
use App\Enums\HttpMethod;
use App\Enums\VideoAudioSource;
use App\Enums\VideoInputMode;
use App\Exceptions\VideoGeneratingException;
use GuzzleHttp\RequestOptions;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Резервный адаптер Runway: переводит статусы задачи в URL, ожидание (null)
 * или VideoGeneratingException, с которой polling job завершается ошибкой.
 */
readonly class Runway extends AsyncVideoGenerator
{
    public function __construct(
        #[Config('services.runway.api_url')] private string $baseUrl,
        #[Config('services.runway.api_key')] private string $token,
        #[Config('services.runway.version')] private string $version,
    ) {}

    public function generate(VideoGenerationRequestData $request): string
    {
        $this->capabilities($request->model)->assertSupports($request);

        return $this->submit(fn(): Response => $this->request('image_to_video', parameters: [
            'model' => $request->model,
            'promptImage' => $request->firstFrameUrl,
            'promptText' => $request->prompt,
            'ratio' => $request->aspectRatio === '16:9' ? '1280:720' : '720:1280',
            'duration' => $request->duration,
        ]), 'id');
    }

    public function capabilities(?string $model = null): VideoCapabilitiesData
    {
        if ($model !== null && $model !== 'gen4_turbo') {
            throw new InvalidArgumentException('Unsupported Runway model.');
        }

        return new VideoCapabilitiesData(
            model: 'gen4_turbo',
            modes: [VideoInputMode::FIRST_FRAME],
            minDuration: 5,
            maxDuration: 10,
            resolutions: ['720p'],
            defaultResolution: '720p',
            defaultAspectRatio: '9:16',
            aspectRatios: ['9:16', '16:9'],
            durations: [5, 10],
        );
    }

    /**
     * @throws ConnectionException
     * @throws VideoGeneratingException
     */
    protected function poll(string $id): ?VideoResultData
    {
        $response = $this->request('tasks/' . rawurlencode($id), HttpMethod::GET);

        $dto = CreationData::from($response->json());

        if ($dto->status === GenerationStatus::FAILED->value) {
            Log::error("Video generation failed: $dto->failure", [
                'id' => $dto->id,
                'createdAt' => $dto->createdAt,
            ]);

            throw new VideoGeneratingException();
        }

        return $dto->status === GenerationStatus::SUCCEEDED->value
            ? new VideoResultData($dto->output[0] ?? throw new VideoGeneratingException('Completed video has no output.'))
            : null;
    }

    public function audioSource(): VideoAudioSource
    {
        return VideoAudioSource::SEPARATE;
    }

    public function pollingAttempts(): int
    {
        return 10;
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

            $response->throw();
        }

        return $response;
    }
}
