<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Hailuo;

use App\Classes\Generators\VideoGenerator\AsyncVideoGenerator;
use App\Data\VideoCapabilitiesData;
use App\Data\VideoGenerationRequestData;
use App\Data\VideoResultData;
use App\Enums\ContentType;
use App\Enums\VideoAudioSource;
use App\Enums\VideoInputMode;
use App\Exceptions\VideoGeneratingException;
use App\Support\Integrations\Payload;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Адаптер Hailuo: создаёт задачу, при polling получает file ID и разрешает его
 * в URL загрузки; отсутствие file ID означает, что результат ещё не готов.
 */
readonly class Hailuo extends AsyncVideoGenerator
{
    public function __construct(
        #[Config('services.hailuo.api_url')] private string $baseUrl,
        #[Config('services.hailuo.api_key')] private string $token,
        #[Config('services.hailuo.group_id')] private string $groupId,
    ) {}

    public function generate(VideoGenerationRequestData $request): string
    {
        $this->capabilities($request->model)->assertSupports($request);

        if ($request->resolution === '1080P' && $request->duration !== 6) {
            throw new InvalidArgumentException('Hailuo 1080P only supports six-second videos.');
        }

        return $this->submit(fn(): Response => $this->client()->post('video_generation', [
            'model' => $request->model,
            'first_frame_image' => $request->firstFrameUrl,
            'prompt' => $request->prompt,
            'duration' => $request->duration,
            'resolution' => $request->resolution,
        ]), 'task_id');
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
            resolutions: [
                '512P',
                '768P',
                '1080P',
            ],
            defaultResolution: '512P',
            durations: [6, 10],
        );
    }

    /**
     * @throws VideoGeneratingException
     * @throws ConnectionException
     */
    protected function poll(string $id): ?VideoResultData
    {
        $response = $this->client()->get('query/video_generation/', ['task_id' => $id]);

        if (in_array($response->json('status'), ['Fail', 'failed'], true)) {
            throw new VideoGeneratingException('Hailuo video generation failed.');
        }

        $fileId = $response->json('file_id');

        if (! $fileId) {
            return null;
        }

        $url = $this->downloadUrl(Payload::string($fileId, 'file_id'));

        if (! $url) {
            throw new VideoGeneratingException('Hailuo completed video has no download URL.');
        }

        return new VideoResultData($url);
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
    private function downloadUrl(string $fileId): ?string
    {
        $url = $this->client()->get('files/retrieve', ['GroupId' => $this->groupId, 'file_id' => $fileId])->json('file.download_url');

        return $url === null ? null : Payload::string($url, 'file.download_url');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->connectTimeout(10)
            ->timeout(60)
            ->withHeaders(['Content-Type' => ContentType::JSON->value])
            ->throw(static function (Response $response): void {
                Log::error('Hailuo API Error', ['status' => $response->status()]);
            });
    }
}
