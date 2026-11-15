<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Hailuo;

use App\Classes\Generators\VideoGenerator\VideoGenerator;
use App\Data\VideoCapabilitiesData;
use App\Data\VideoGenerationRequestData;
use App\Data\VideoResultData;
use App\Enums\ContentType;
use App\Enums\HttpMethod;
use App\Enums\VideoAudioSource;
use App\Enums\VideoInputMode;
use App\Exceptions\VideoGeneratingException;
use App\Exceptions\VideoGenerationPendingException;
use App\Exceptions\VideoGenerationUncertainException;
use App\Support\Integrations\Payload;
use GuzzleHttp\RequestOptions;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Адаптер Hailuo: создаёт задачу, при polling получает file ID и разрешает его
 * в URL загрузки; отсутствие file ID означает, что результат ещё не готов.
 */
readonly class Hailuo implements VideoGenerator
{
    public function __construct(
        #[Config('services.hailuo.api_url')] private string $baseUrl,
        #[Config('services.hailuo.api_key')] private string $token,
        #[Config('services.hailuo.group_id')] private string $groupId,
    ) {}

    /**
     * @throws ConnectionException
     */
    public function generate(VideoGenerationRequestData $request): string
    {
        $this->capabilities($request->model)->assertSupports($request);

        if ($request->resolution === '1080P' && $request->duration !== 6) {
            throw new InvalidArgumentException('Hailuo 1080P only supports six-second videos.');
        }

        try {
            $response = $this
                ->request('video_generation', parameters: [
                    'model' => $request->model,
                    'first_frame_image' => $request->firstFrameUrl,
                    'prompt' => $request->prompt,
                    'duration' => $request->duration,
                    'resolution' => $request->resolution,
                ])
                ->json('task_id');

            return Payload::string($response, 'task_id');
        } catch (ConnectionException|UnexpectedValueException $exception) {
            throw new VideoGenerationUncertainException('Video submission outcome is unknown.', $exception->getCode(), previous: $exception);
        } catch (RequestException $exception) {
            if ($exception->response->serverError()) {
                throw new VideoGenerationUncertainException('Video submission outcome is unknown.', $exception->getCode(), previous: $exception);
            }

            throw $exception;
        }
    }

    public function capabilities(?string $model = null): VideoCapabilitiesData
    {
        if ($model !== null && $model !== 'MiniMax-Hailuo-02') {
            throw new InvalidArgumentException('Unsupported Hailuo model.');
        }

        return new VideoCapabilitiesData(
            model: 'MiniMax-Hailuo-02',
            modes: [VideoInputMode::FIRST_FRAME],
            minDuration: 6,
            maxDuration: 10,
            resolutions: ['512P', '768P', '1080P'],
            defaultResolution: '768P',
            durations: [6, 10],
        );
    }

    /**
     * @throws VideoGeneratingException
     * @throws ConnectionException
     */
    public function check(string $id): ?VideoResultData
    {
        try {
            $response = $this->request('query/video_generation/?task_id=' . rawurlencode($id), HttpMethod::GET);

            if (in_array($response->json('status'), ['Fail', 'failed'], true)) {
                throw new VideoGeneratingException('Hailuo video generation failed.');
            }

            $fileId = $response->json('file_id');

            if (! $fileId) {
                return null;
            }

            $url = $this->get(Payload::string($fileId, 'file_id'));

            if (! $url) {
                throw new VideoGeneratingException('Hailuo completed video has no download URL.');
            }

            return new VideoResultData($url);
        } catch (ConnectionException $exception) {
            throw new VideoGenerationPendingException('Video status is temporarily unavailable.', $exception->getCode(), previous: $exception);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 429 || $exception->response->serverError()) {
                throw new VideoGenerationPendingException('Video status is temporarily unavailable.', $exception->getCode(), previous: $exception);
            }

            throw $exception;
        }
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
     * @throws ConnectionException
     */
    public function get(string $fileId): ?string
    {
        $url = $this->request("files/retrieve?GroupId=$this->groupId&file_id=$fileId", HttpMethod::GET)->json('file.download_url');

        return $url === null ? null : Payload::string($url, 'file.download_url');
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
                'Content-Type' => ContentType::JSON->value,
            ])
            ->send(
                $method->value,
                "$this->baseUrl/$url",
                $parameters !== []
                    ? [RequestOptions::JSON => $parameters]
                    : [],
            );

        if ($response->failed()) {
            Log::error('Hailuo API Error', ['status' => $response->status()]);

            $response->throw();
        }

        return $response;
    }
}
