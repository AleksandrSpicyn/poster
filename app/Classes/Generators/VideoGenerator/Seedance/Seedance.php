<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Seedance;

use App\Classes\Generators\VideoGenerator\Seedance\Enums\GenerationStatus;
use App\Classes\Generators\VideoGenerator\Seedance\Enums\Model;
use App\Classes\Generators\VideoGenerator\Seedance\Enums\Resolution;
use App\Classes\Generators\VideoGenerator\VideoGenerator;
use App\Data\VideoResultData;
use App\Enums\VideoAudioSource;
use App\Exceptions\VideoGeneratingException;
use App\Exceptions\VideoGenerationPendingException;
use App\Exceptions\VideoGenerationUncertainException;
use App\Settings\GeneralSettings;
use App\Support\Integrations\Payload;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Асинхронный BytePlus ModelArk: первый кадр → видео со встроенным звуком. */
readonly class Seedance implements VideoGenerator
{
    public function __construct(
        #[Config('services.seedance.api_url')] private string $baseUrl,
        #[Config('services.seedance.api_key')] private string $token,
        #[Config('services.seedance.model')] private string $model,
        #[Config('services.seedance.resolution')] private string $resolution,
        private GeneralSettings $settings,
        #[Storage('public')] private FilesystemAdapter $disk,
    ) {}

    public function generate(string $imageUrl, string $prompt): string
    {
        $model = Model::from($this->model);
        $resolution = Resolution::from($this->resolution);

        if ($this->settings->duration < 4 || $this->settings->duration > $model->maxDuration()) {
            throw new InvalidArgumentException('Unsupported Seedance video duration.');
        }

        if (! $model->supports($resolution)) {
            throw new InvalidArgumentException('Unsupported Seedance model resolution.');
        }

        $firstFrame = $this->firstFrame($imageUrl);

        try {
            $response = $this->request()
                ->post('contents/generations/tasks', [
                    'model' => $model->value,
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $firstFrame], 'role' => 'first_frame'],
                    ],
                    'resolution' => $resolution->value,
                    'ratio' => 'adaptive',
                    'duration' => $this->settings->duration,
                    'generate_audio' => true,
                    'watermark' => false,
                    'execution_expires_after' => 3600,
                ])
                ->throw();

            return Payload::string($response->json('id'), 'id');
        } catch (ConnectionException|UnexpectedValueException $exception) {
            throw new VideoGenerationUncertainException('Video submission outcome is unknown.', $exception->getCode(), previous: $exception);
        } catch (RequestException $exception) {
            if ($exception->response->serverError()) {
                throw new VideoGenerationUncertainException('Video submission outcome is unknown.', $exception->getCode(), previous: $exception);
            }

            throw $exception;
        }
    }

    public function check(string $id): ?VideoResultData
    {
        try {
            $response = $this->request()
                ->get('contents/generations/tasks/' . rawurlencode($id))
                ->throw();
        } catch (ConnectionException $exception) {
            throw new VideoGenerationPendingException('Video status is temporarily unavailable.', $exception->getCode(), previous: $exception);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 429 || $exception->response->serverError()) {
                throw new VideoGenerationPendingException('Video status is temporarily unavailable.', $exception->getCode(), previous: $exception);
            }

            throw $exception;
        }

        $status = GenerationStatus::from(Payload::string($response->json('status'), 'status'));

        if ($status === GenerationStatus::QUEUED || $status === GenerationStatus::RUNNING) {
            return null;
        }

        if ($status !== GenerationStatus::SUCCEEDED) {
            throw new VideoGeneratingException('Seedance task ended with status: ' . $status->value);
        }

        if ($response->json('generate_audio') !== true) {
            throw new VideoGeneratingException('Seedance returned a video without the requested audio.');
        }

        $url = Payload::string($response->json('content.video_url'), 'content.video_url');

        return new VideoResultData($url, $url);
    }

    public function audioSource(): VideoAudioSource
    {
        return VideoAudioSource::EMBEDDED;
    }

    public function pollingAttempts(): int
    {
        return 61;
    }

    private function request(): PendingRequest
    {
        if (trim($this->token) === '') {
            throw new RuntimeException('Seedance API key is not configured.');
        }

        return Http::baseUrl(rtrim($this->baseUrl, '/') . '/')
            ->withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(60);
    }

    /** Передаёт сохранённый кадр напрямую: localhost и закрытые сети недоступны BytePlus. */
    private function firstFrame(string $imageUrl): string
    {
        $prefix = rtrim($this->disk->url('generated-images'), '/') . '/';

        if (! str_starts_with($imageUrl, $prefix)) {
            return $imageUrl;
        }

        $fileName = substr($imageUrl, strlen($prefix));

        if (preg_match('/\A[a-zA-Z0-9_-]+\.(png|jpe?g|webp)\z/', $fileName, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid generated first-frame path.');
        }

        $path = 'generated-images/' . $fileName;
        $size = $this->disk->size($path);

        if ($size < 1 || $size >= 30_000_000) {
            throw new InvalidArgumentException('Seedance first frame must be smaller than 30 MB and nonempty.');
        }

        $mime = match ($matches[1]) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(Payload::string($this->disk->get($path), 'first_frame'));
    }
}
